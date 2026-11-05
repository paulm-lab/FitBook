<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails transactionnels de FitBook (template Twig unique : emails/message.html.twig).
 *
 * Chaque méthode renvoie le résultat réel de l'envoi : envoyé directement ou mis en file
 * Messenger. Une exception signifie un échec : l'appelant décide du message à afficher.
 */
final class FitBookMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MailQueueTracker $queueTracker,
        private readonly string $senderAddress,
    ) {
    }

    /** E-mail de test de la configuration (route /api/dev/mail-test). */
    public function sendTest(string $recipient): EmailDeliveryStatus
    {
        return $this->send(
            $recipient,
            'Test e-mail FitBook',
            'Votre configuration e-mail fonctionne',
            'Cet e-mail confirme que FitBook peut envoyer des messages.',
        );
    }

    /** Lien de confirmation de l'adresse e-mail après l'inscription. */
    public function sendVerification(User $user, string $verificationUrl): EmailDeliveryStatus
    {
        return $this->send(
            (string) $user->getEmail(),
            'Vérifiez votre adresse e-mail FitBook',
            'Confirmez votre adresse e-mail',
            sprintf('Bonjour %s, confirmez votre adresse pour activer votre compte FitBook. Ce lien expire dans 24 heures.', $user->getFirstName()),
            $verificationUrl,
            'Vérifier mon adresse',
        );
    }

    /** Bienvenue, une fois l'adresse confirmée. */
    public function sendRegistrationConfirmation(User $user): EmailDeliveryStatus
    {
        return $this->send(
            (string) $user->getEmail(),
            'Votre compte FitBook est activé',
            'Bienvenue chez FitBook',
            sprintf('Bonjour %s, votre adresse est vérifiée et votre compte est maintenant actif. Vous pouvez découvrir les séances disponibles.', $user->getFirstName()),
        );
    }

    /** Lien de réinitialisation du mot de passe (à usage unique, valable 1 heure). */
    public function sendPasswordReset(User $user, string $resetUrl): EmailDeliveryStatus
    {
        return $this->send(
            (string) $user->getEmail(),
            'Réinitialisation de votre mot de passe FitBook',
            'Choisissez un nouveau mot de passe',
            sprintf('Bonjour %s, une réinitialisation de votre mot de passe a été demandée. Ce lien est valable 1 heure et ne peut servir qu’une fois. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail : votre mot de passe actuel reste valable.', $user->getFirstName()),
            $resetUrl,
            'Réinitialiser mon mot de passe',
        );
    }

    public function sendReservationConfirmation(Reservation $reservation): EmailDeliveryStatus
    {
        return $this->sendReservationNotice(
            $reservation,
            'Réservation confirmée sur FitBook',
            'Votre réservation est confirmée',
            'Votre réservation FitBook a bien été enregistrée.',
        );
    }

    public function sendReservationCancellation(Reservation $reservation): EmailDeliveryStatus
    {
        return $this->sendReservationNotice(
            $reservation,
            'Annulation de votre réservation FitBook',
            'Votre réservation est annulée',
            'Votre réservation FitBook a été annulée.',
        );
    }

    /** La séance a été annulée par son coach ou son gestionnaire (RG-12). */
    public function sendSessionCancelled(Reservation $reservation): EmailDeliveryStatus
    {
        return $this->sendReservationNotice(
            $reservation,
            'Séance annulée sur FitBook',
            'Votre séance est annulée',
            'L’organisateur a annulé cette séance. Votre réservation est annulée et aucun montant ne vous sera demandé. Découvrez les autres séances disponibles sur FitBook.',
        );
    }

    /** Rappel envoyé environ 24 h avant la séance (commande app:send-reminders, US-S08). */
    public function sendReminder(Reservation $reservation): EmailDeliveryStatus
    {
        return $this->sendReservationNotice(
            $reservation,
            'Rappel : votre séance FitBook a lieu demain',
            'C’est bientôt l’heure de votre séance',
            'Votre séance approche. Pensez à votre tenue et présentez le QR code de votre réservation à l’accueil.',
        );
    }

    /** E-mail de réservation avec le récapitulatif de la séance. */
    private function sendReservationNotice(Reservation $reservation, string $subject, string $title, string $message): EmailDeliveryStatus
    {
        $session = $reservation->getSession();
        $timezone = new \DateTimeZone(CataloguePresenter::LOCAL_TIMEZONE);
        $details = [];

        if ($session !== null) {
            $details = [
                'Séance' => (string) $session->getTitle(),
                // Dates stockées en UTC : affichées à l'heure de Bruxelles, où sont les salles.
                'Date' => $session->getStartAt()?->setTimezone($timezone)->format('d/m/Y') ?? '',
                'Horaire' => sprintf('%s – %s', $session->getStartAt()?->setTimezone($timezone)->format('H:i'), $session->getEndAt()?->setTimezone($timezone)->format('H:i')),
                'Lieu' => $session->getGym()?->getName() ?? 'À préciser par le coach',
                'Prix' => $session->getPrice() . ' €',
            ];
        }

        return $this->send((string) $reservation->getUser()?->getEmail(), $subject, $title, $message, null, null, $details);
    }

    /** @param array<string, string> $details */
    private function send(
        string $recipient,
        string $subject,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        array $details = [],
    ): EmailDeliveryStatus {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L’adresse destinataire est absente ou invalide.');
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, 'FitBook'))
            ->to($recipient)
            ->subject($subject)
            ->htmlTemplate('emails/message.html.twig')
            ->context([
                'title' => $title,
                'message' => $message,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel,
                'details' => $details,
            ]);

        // Le tracker indique si Messenger a mis l'e-mail en file au lieu de l'envoyer.
        $this->queueTracker->reset();
        $this->mailer->send($email);

        return $this->queueTracker->wasQueued() ? EmailDeliveryStatus::Queued : EmailDeliveryStatus::Sent;
    }
}
