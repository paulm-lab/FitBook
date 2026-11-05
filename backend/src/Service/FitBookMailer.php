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

    /** E-mail de réservation avec le récapitulatif de la séance. */
    private function sendReservationNotice(Reservation $reservation, string $subject, string $title, string $message): EmailDeliveryStatus
    {
        $session = $reservation->getSession();
        $details = [];

        if ($session !== null) {
            $details = [
                'Séance' => (string) $session->getTitle(),
                'Date' => $session->getStartAt()?->format('d/m/Y') ?? '',
                'Horaire' => sprintf('%s – %s', $session->getStartAt()?->format('H:i'), $session->getEndAt()?->format('H:i')),
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
