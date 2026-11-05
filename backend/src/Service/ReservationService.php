<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Http\ApiException;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Réservation et annulation (RG-03 à RG-05, RG-12).
 *
 * Sur MySQL, les règles sont appliquées par les procédures stockées sp_reserver_seance et
 * sp_annuler_reservation : elles ouvrent leur propre transaction et verrouillent la séance
 * (SELECT … FOR UPDATE), donc deux demandes simultanées sur la dernière place ne peuvent pas
 * aboutir toutes les deux. Sur la base SQLite des tests, les mêmes règles sont appliquées en
 * PHP, dans une transaction Doctrine avec verrou pessimiste, avec les mêmes codes de résultat.
 *
 * Les codes renvoyés par les procédures sont traduits en erreurs HTTP (ApiException).
 */
final class ReservationService
{
    // Codes des procédures (database/procedures/*.sql).
    private const OK = 0;
    private const RESERVE_ERRORS = [
        1 => [404, 'Séance introuvable.'],
        2 => [409, 'Cette séance n’est plus disponible.'],
        3 => [409, 'Vous avez déjà réservé cette séance.'],
        4 => [409, 'Il ne reste plus de place.'],
    ];
    private const CANCEL_ERRORS = [
        1 => [404, 'Réservation introuvable.'],
        2 => [403, 'Cette réservation ne vous appartient pas.'],
        3 => [409, 'Il est trop tard pour annuler : la séance commence dans moins de 2 heures.'],
        4 => [409, 'Cette réservation est déjà annulée ou terminée.'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservations,
        private readonly SessionRepository $sessions,
        private readonly FitBookMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reserve(User $user, int $sessionId): Reservation
    {
        if ($this->reservations->supportsStoredProcedures()) {
            ['code' => $code, 'reservationId' => $id] = $this->reservations->callReserveProcedure((int) $user->getId(), $sessionId);
            $this->failOn($code, self::RESERVE_ERRORS);
            $reservation = $this->reservations->find($id);
            // La procédure a écrit directement en base : on recharge l'état à jour.
            $this->em->refresh($reservation);
        } else {
            $reservation = $this->reserveWithDoctrine($user, $sessionId);
        }

        // Jeton du QR code présenté à l'accueil (bonus) : aléatoire, non devinable.
        $reservation->setQrToken(bin2hex(random_bytes(16)));
        $this->inApp($reservation->getUser(), 'reservation_confirmee', sprintf('Réservation confirmée : %s.', $this->label($reservation)));
        $this->em->flush();

        $this->notify(fn () => $this->mailer->sendReservationConfirmation($reservation), 'confirmation de réservation');

        return $reservation;
    }

    /** Annulation par le sportif lui-même, au plus tard 2 h avant la séance (RG-05). */
    public function cancel(User $user, Reservation $reservation): Reservation
    {
        if ($this->reservations->supportsStoredProcedures()) {
            $this->failOn($this->reservations->callCancelProcedure((int) $reservation->getId(), (int) $user->getId()), self::CANCEL_ERRORS);
            $this->em->refresh($reservation);
        } else {
            $this->failOn($this->cancelWithDoctrine($user, $reservation), self::CANCEL_ERRORS);
        }

        $this->inApp($reservation->getUser(), 'reservation_annulee', sprintf('Réservation annulée : %s.', $this->label($reservation)));
        $this->em->flush();
        $this->notify(fn () => $this->mailer->sendReservationCancellation($reservation), 'annulation de réservation');

        return $reservation;
    }

    /** Annulation par l'administrateur (correction des données) : sans condition de délai. */
    public function cancelByAdmin(Reservation $reservation): Reservation
    {
        if ($reservation->getStatus() !== ReservationStatus::Confirmee) {
            throw ApiException::conflict('Cette réservation est déjà annulée ou terminée.');
        }

        $reservation->setStatus(ReservationStatus::Annulee)->setCancelledAt(new \DateTimeImmutable());
        $this->inApp($reservation->getUser(), 'reservation_annulee', sprintf('Votre réservation « %s » a été annulée par l’équipe FitBook.', $this->label($reservation)));
        $this->em->flush();
        $this->notify(fn () => $this->mailer->sendReservationCancellation($reservation), 'annulation de réservation');

        return $reservation;
    }

    /**
     * Annulation d'une séance par son coach ou son gestionnaire (RG-12) : toutes les réservations
     * confirmées sont annulées, quel que soit le délai, et chaque sportif est prévenu.
     *
     * @return int nombre de sportifs prévenus
     */
    public function cancelSession(Session $session): int
    {
        if ($session->getStatus() === SessionStatus::Annulee) {
            throw ApiException::conflict('Cette séance est déjà annulée.');
        }

        $now = new \DateTimeImmutable();
        $session->setStatus(SessionStatus::Annulee);
        $cancelled = [];
        foreach ($session->getReservations() as $reservation) {
            if ($reservation->getStatus() === ReservationStatus::Confirmee) {
                $reservation->setStatus(ReservationStatus::Annulee)->setCancelledAt($now);
                $this->inApp($reservation->getUser(), 'seance_annulee', sprintf('La séance « %s » a été annulée par l’organisateur.', $this->label($reservation)));
                $cancelled[] = $reservation;
            }
        }
        $this->em->flush();

        foreach ($cancelled as $reservation) {
            $this->notify(fn () => $this->mailer->sendSessionCancelled($reservation), 'annulation de séance');
        }

        return \count($cancelled);
    }

    // -------------------------------------------------------------------------
    // Même logique que les procédures, pour la base SQLite des tests
    // -------------------------------------------------------------------------

    private function reserveWithDoctrine(User $user, int $sessionId): Reservation
    {
        $this->em->beginTransaction();
        try {
            $session = $this->em->find(Session::class, $sessionId, LockMode::PESSIMISTIC_WRITE);
            $existing = $session ? $this->reservations->findOneBy(['user' => $user, 'session' => $session]) : null;
            $taken = $session ? ($this->sessions->confirmedCounts([$session])[$session->getId()] ?? 0) : 0;

            $code = match (true) {
                $session === null => 1,
                $session->getStatus() !== SessionStatus::Active || $session->getStartAt() <= new \DateTimeImmutable() => 2,
                $existing?->getStatus() === ReservationStatus::Confirmee => 3,
                $taken >= $session->getCapacity() => 4,
                default => self::OK,
            };
            if ($code !== self::OK) {
                $this->em->rollback();
                $this->failOn($code, self::RESERVE_ERRORS);
            }

            // Une seule ligne par couple (sportif, séance) : réactivée si elle était annulée.
            $reservation = $existing ?? (new Reservation())->setUser($user)->setSession($session);
            $reservation->setStatus(ReservationStatus::Confirmee)->setCreatedAt(new \DateTimeImmutable())->setCancelledAt(null);
            $this->em->persist($reservation);
            $this->em->flush();
            $this->em->commit();

            return $reservation;
        } catch (ApiException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw $exception;
        }
    }

    private function cancelWithDoctrine(User $user, Reservation $reservation): int
    {
        $now = new \DateTimeImmutable();
        $code = match (true) {
            $reservation->getUser() !== $user => 2,
            $reservation->getStatus() !== ReservationStatus::Confirmee => 4,
            !$reservation->isCancellable($now) => 3,
            default => self::OK,
        };

        if ($code === self::OK) {
            $reservation->setStatus(ReservationStatus::Annulee)->setCancelledAt($now);
            $this->em->flush();
        }

        return $code;
    }

    // -------------------------------------------------------------------------

    /** @param array<int, array{0: int, 1: string}> $errors */
    private function failOn(int $code, array $errors): void
    {
        if ($code === self::OK) {
            return;
        }
        [$status, $message] = $errors[$code] ?? [500, 'Résultat inattendu de la base de données.'];

        throw new ApiException($status, $message);
    }

    /** Notification interne (bonus), enregistrée avec la prochaine écriture. */
    private function inApp(?User $user, string $type, string $message): void
    {
        if ($user === null) {
            return;
        }
        $this->em->persist((new Notification())->setUser($user)->setType($type)->setMessage(mb_substr($message, 0, 255)));
    }

    private function label(Reservation $reservation): string
    {
        $session = $reservation->getSession();
        $start = $session?->getStartAt()?->setTimezone(new \DateTimeZone(CataloguePresenter::LOCAL_TIMEZONE));

        return sprintf('%s du %s', $session?->getTitle() ?? 'séance', $start?->format('d/m/Y à H:i') ?? '');
    }

    /** Un e-mail qui échoue ne doit jamais annuler l'opération déjà enregistrée. */
    private function notify(callable $send, string $what): void
    {
        try {
            $send();
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf('Opération enregistrée, mais échec de l’e-mail de %s.', $what), [
                'exception_class' => $exception::class,
            ]);
        }
    }
}
