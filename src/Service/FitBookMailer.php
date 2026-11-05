<?php

declare(strict_types=1);
namespace App\Service;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
final class FitBookMailer
{
    public function __construct(private readonly MailerInterface $mailer, private readonly MailQueueTracker $queueTracker, private readonly string $senderAddress) {}
    public function sendTest(string $recipient): EmailDeliveryStatus { return $this->send($recipient, 'Test e-mail FitBook', 'Votre configuration e-mail fonctionne', 'Cet e-mail confirme que FitBook peut envoyer des messages via Mailtrap Email Sandbox.'); }
    public function sendVerification(Utilisateur $user, string $verificationUrl): EmailDeliveryStatus { return $this->send($user->getEmail() ?? '', 'Vérifiez votre adresse e-mail FitBook', 'Confirmez votre adresse e-mail', 'Bonjour ' . ($user->getPrenom() ?? '') . ', confirmez votre adresse pour activer votre compte FitBook. Ce lien expire dans 24 heures.', $verificationUrl, 'Vérifier mon adresse'); }
    public function sendRegistrationConfirmation(Utilisateur $user): EmailDeliveryStatus { return $this->send($user->getEmail() ?? '', 'Votre compte FitBook est activé', 'Bienvenue chez FitBook', 'Bonjour ' . ($user->getPrenom() ?? '') . ', votre adresse est vérifiée et votre compte est maintenant actif. Vous pouvez découvrir les séances disponibles.'); }
    public function sendReservationConfirmation(Reservation $reservation): EmailDeliveryStatus { return $this->sendReservationNotice($reservation, 'Réservation confirmée sur FitBook', 'Votre réservation est confirmée', 'Votre réservation FitBook a bien été enregistrée.'); }
    public function sendReservationCancellation(Reservation $reservation): EmailDeliveryStatus { return $this->sendReservationNotice($reservation, 'Annulation de votre réservation FitBook', 'Votre réservation est annulée', 'Votre réservation FitBook a été annulée.'); }
    private function sendReservationNotice(Reservation $reservation, string $subject, string $title, string $message): EmailDeliveryStatus
    {
        $session = $reservation->getSeance(); $details = [];
        if ($session !== null) $details = ['Séance' => $session->getTitre() ?? 'Séance FitBook', 'Date' => $session->getDateSeance()?->format('d/m/Y') ?? '', 'Horaire' => trim(($session->getHeureDebut()?->format('H:i') ?? '') . ' – ' . ($session->getHeureFin()?->format('H:i') ?? '')), 'Places' => (string) ($reservation->getNombrePlaces() ?? 0), 'Montant' => ($reservation->getMontantTotal() ?? '0.00') . ' €'];
        return $this->send($reservation->getUtilisateur()?->getEmail() ?? '', $subject, $title, $message, null, null, $details);
    }
    /** @param array<string, string> $details */
    private function send(string $recipient, string $subject, string $title, string $message, ?string $actionUrl = null, ?string $actionLabel = null, array $details = []): EmailDeliveryStatus
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('L’adresse destinataire est absente ou invalide.');
        $email = (new TemplatedEmail())->from(new Address($this->senderAddress, 'FitBook'))->to($recipient)->subject($subject)->htmlTemplate('emails/message.html.twig')->context(['title' => $title, 'message' => $message, 'actionUrl' => $actionUrl, 'actionLabel' => $actionLabel, 'details' => $details]);
        // Une exception signifie que l'e-mail n'est ni envoyé ni en file : l'appelant décide du message à afficher.
        $this->queueTracker->reset();
        $this->mailer->send($email);

        return $this->queueTracker->wasQueued() ? EmailDeliveryStatus::Queued : EmailDeliveryStatus::Sent;
    }
}