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
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function reserve(User $user, int $sessionId): Reservation
    {
        // Séance d'un coach bloqué : retirée du catalogue, donc indisponible.
        $session = $this->sessions->find($sessionId);
        if ($session !== null && !$session->hasAvailableCoach()) {
            $this->failOn(2, self::RESERVE_ERRORS);
        }

        if ($this->reservations->supportsStoredProcedures()) {
            ['code' => $code, 'reservationId' => $id] = $this->reservations->callReserveProcedure((int) $user->getId(), $sessionId);
            $this->failOn($code, self::RESERVE_ERRORS);
            $reservation = $this->reservations->find($id);
        } else {
            $reservation = $this->reserveWithDoctrine($user, $sessionId);
        }

        // La place est prise (transaction validée) : la suite ne doit plus faire échouer la requête.
        $this->assignQrToken($reservation);
        try {
            $this->inApp($reservation, 'reservation_confirmee', 'Réservation confirmée : {session}.');
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Réservation enregistrée, mais échec de la notification interne.', [
                'reservation' => $reservation->getId(),
                'exception_class' => $exception::class,
            ]);
        }

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

        $this->inApp($reservation, 'reservation_annulee', 'Réservation annulée : {session}.');
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
        $this->inApp($reservation, 'reservation_annulee', 'Votre réservation « {session} » a été annulée par l’équipe FitBook.');
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
        // Séance verrouillée (même verrou que sp_reserver_seance) avant de lire les réservations :
        // une réservation simultanée attend, puis trouve la séance annulée.
        $this->em->beginTransaction();
        try {
            $this->em->refresh($session, LockMode::PESSIMISTIC_WRITE);
            $now = new \DateTimeImmutable();
            if ($session->getStatus() === SessionStatus::Annulee) {
                throw ApiException::conflict('Cette séance est déjà annulée.');
            }
            if ($session->getStartAt() <= $now) {
                throw ApiException::conflict('Cette séance a déjà commencé : elle ne peut plus être annulée.');
            }

            $session->setStatus(SessionStatus::Annulee);
            $cancelled = [];
            foreach ($this->reservations->findBy(['session' => $session, 'status' => ReservationStatus::Confirmee]) as $reservation) {
                $reservation->setStatus(ReservationStatus::Annulee)->setCancelledAt($now);
                $this->inApp($reservation, 'seance_annulee', 'La séance « {session} » a été annulée par l’organisateur.');
                $cancelled[] = $reservation;
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $exception) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw $exception;
        }

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
            // Rappel remis à zéro : une réservation réactivée doit recevoir son rappel.
            $reservation->setStatus(ReservationStatus::Confirmee)->setCreatedAt(new \DateTimeImmutable())->setCancelledAt(null)->setReminderSentAt(null);
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

    /**
     * Jeton du QR code présenté à l'accueil (bonus) : aléatoire, non devinable. Écrit par une
     * requête directe, avec un nouvel essai (autre jeton) en cas d'échec ; s'il échoue encore,
     * la réservation reste valable sans QR code et l'incident est journalisé. L'entité est
     * ensuite relue en base (la procédure stockée y a aussi écrit directement).
     */
    private function assignQrToken(Reservation $reservation): void
    {
        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $this->reservations->storeQrToken((int) $reservation->getId(), bin2hex(random_bytes(16)));
                break;
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf('Réservation enregistrée, mais échec de l’enregistrement du jeton QR (essai %d).', $attempt), [
                    'reservation' => $reservation->getId(),
                    'exception_class' => $exception::class,
                ]);
            }
        }

        try {
            $this->em->refresh($reservation);
        } catch (\Throwable $exception) {
            $this->logger->error('Réservation enregistrée, mais relecture impossible.', [
                'reservation' => $reservation->getId(),
                'exception_class' => $exception::class,
            ]);
        }
    }

    /** @param array<int, array{0: int, 1: string}> $errors */
    private function failOn(int $code, array $errors): void
    {
        if ($code === self::OK) {
            return;
        }
        [$status, $message] = $errors[$code] ?? [500, 'Résultat inattendu de la base de données.'];

        throw new ApiException($status, $message);
    }

    /**
     * Notification interne (bonus), enregistrée avec la prochaine écriture. Le message est
     * rédigé dans la langue du sportif (clé française, {session} remplacé par la séance).
     */
    private function inApp(Reservation $reservation, string $type, string $message): void
    {
        $user = $reservation->getUser();
        if ($user === null) {
            return;
        }
        $text = $this->translator->trans($message, ['{session}' => $this->label($reservation, $user->getLocale())], 'messages', $user->getLocale());
        $this->em->persist((new Notification())->setUser($user)->setType($type)->setMessage(mb_substr($text, 0, 255)));
    }

    private function label(Reservation $reservation, string $locale): string
    {
        $session = $reservation->getSession();
        $start = $session?->getStartAt()?->setTimezone(new \DateTimeZone(CataloguePresenter::LOCAL_TIMEZONE));

        return $this->translator->trans('{title} du {date} à {time}', [
            '{title}' => $session?->getTitle() ?? $this->translator->trans('séance', [], 'messages', $locale),
            '{date}' => $start?->format('d/m/Y') ?? '',
            '{time}' => $start?->format('H:i') ?? '',
        ], 'messages', $locale);
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
