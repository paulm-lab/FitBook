<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * E-mails transactionnels de FitBook (template Twig unique : emails/message.html.twig).
 *
 * Chaque e-mail est rédigé dans la langue du destinataire (User::getLocale(), fr ou en) :
 * les textes français servent de clés de traduction (translations/messages.en.yaml).
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
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** E-mail de test de la configuration (route /api/dev/mail-test), dans la langue de la requête. */
    public function sendTest(string $recipient): EmailDeliveryStatus
    {
        return $this->send(
            $recipient,
            $this->translator->getLocale(),
            'Test e-mail FitBook',
            'Votre configuration e-mail fonctionne',
            'Cet e-mail confirme que FitBook peut envoyer des messages.',
        );
    }

    /** Bienvenue, juste après l'inscription. */
    public function sendRegistrationConfirmation(User $user): EmailDeliveryStatus
    {
        return $this->send(
            (string) $user->getEmail(),
            $user->getLocale(),
            'Bienvenue sur FitBook',
            'Bienvenue chez FitBook',
            'Bonjour {name}, votre compte est créé et vous pouvez dès maintenant vous connecter pour découvrir les séances disponibles.',
            ['{name}' => (string) $user->getFirstName()],
        );
    }

    /** Lien de réinitialisation du mot de passe (à usage unique, valable 1 heure). */
    public function sendPasswordReset(User $user, string $resetUrl): EmailDeliveryStatus
    {
        return $this->send(
            (string) $user->getEmail(),
            $user->getLocale(),
            'Réinitialisation de votre mot de passe FitBook',
            'Choisissez un nouveau mot de passe',
            'Bonjour {name}, une réinitialisation de votre mot de passe a été demandée. Ce lien est valable 1 heure et ne peut servir qu’une fois. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail : votre mot de passe actuel reste valable.',
            ['{name}' => (string) $user->getFirstName()],
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

    /** E-mail de réservation avec le récapitulatif de la séance, dans la langue du sportif. */
    private function sendReservationNotice(Reservation $reservation, string $subject, string $title, string $message): EmailDeliveryStatus
    {
        $session = $reservation->getSession();
        $locale = $reservation->getUser()?->getLocale() ?? 'fr';
        $timezone = new \DateTimeZone(CataloguePresenter::LOCAL_TIMEZONE);
        $details = [];

        if ($session !== null) {
            $label = fn (string $text): string => $this->translator->trans($text, [], 'messages', $locale);
            $details = [
                $label('Séance') => (string) $session->getTitle(),
                // Dates stockées en UTC : affichées à l'heure de Bruxelles, où sont les salles.
                $label('Date') => $session->getStartAt()?->setTimezone($timezone)->format('d/m/Y') ?? '',
                $label('Horaire') => sprintf('%s – %s', $session->getStartAt()?->setTimezone($timezone)->format('H:i'), $session->getEndAt()?->setTimezone($timezone)->format('H:i')),
                $label('Lieu') => $session->getGym()?->getName() ?? $label('À préciser par le coach'),
                $label('Prix') => $session->getPrice() . ' €',
            ];
        }

        return $this->send((string) $reservation->getUser()?->getEmail(), $locale, $subject, $title, $message, details: $details);
    }

    /**
     * Sujet, titre, message et bouton sont des clés françaises, traduites ici dans la langue
     * du destinataire ; le template traduit ses propres mentions fixes avec la même langue.
     *
     * @param array<string, string> $parameters paramètres du message ({name}…)
     * @param array<string, string> $details    récapitulatif, libellés déjà traduits
     */
    private function send(
        string $recipient,
        string $locale,
        string $subject,
        string $title,
        string $message,
        array $parameters = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        array $details = [],
    ): EmailDeliveryStatus {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L’adresse destinataire est absente ou invalide.');
        }

        $trans = fn (string $text, array $params = []): string => $this->translator->trans($text, $params, 'messages', $locale);

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, 'FitBook'))
            ->to($recipient)
            ->subject($trans($subject))
            ->htmlTemplate('emails/message.html.twig')
            // Rendu du template dans cette langue, même s'il a lieu plus tard (worker Messenger).
            ->locale($locale)
            ->context([
                'locale' => $locale,
                'title' => $trans($title),
                'message' => $trans($message, $parameters),
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel !== null ? $trans($actionLabel) : null,
                'details' => $details,
            ]);

        // Le tracker indique si Messenger a mis l'e-mail en file au lieu de l'envoyer.
        $this->queueTracker->reset();
        $this->mailer->send($email);

        return $this->queueTracker->wasQueued() ? EmailDeliveryStatus::Queued : EmailDeliveryStatus::Sent;
    }
}
