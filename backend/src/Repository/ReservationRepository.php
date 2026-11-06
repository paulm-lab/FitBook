<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : réservations, dont les appels aux procédures stockées
 * sp_reserver_seance et sp_annuler_reservation (SQL natif, paramètres liés).
 *
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /** Les procédures stockées n'existent que sur MySQL (pas sur la base SQLite des tests). */
    public function supportsStoredProcedures(): bool
    {
        return $this->getEntityManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }

    /**
     * CALL sp_reserver_seance : la procédure gère sa propre transaction et verrouille la séance.
     *
     * @return array{code: int, reservationId: int|null} code 0 = réservée (voir database/procedures)
     */
    public function callReserveProcedure(int $userId, int $sessionId): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement('CALL sp_reserver_seance(?, ?, @fitbook_code, @fitbook_reservation)', [$userId, $sessionId]);
        $row = $connection->fetchAssociative('SELECT @fitbook_code AS code, @fitbook_reservation AS reservation_id');

        return [
            'code' => (int) $row['code'],
            'reservationId' => $row['reservation_id'] === null ? null : (int) $row['reservation_id'],
        ];
    }

    /** CALL sp_annuler_reservation : 0 = annulée (voir database/procedures). */
    public function callCancelProcedure(int $reservationId, int $userId): int
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement('CALL sp_annuler_reservation(?, ?, @fitbook_code)', [$reservationId, $userId]);

        return (int) $connection->fetchOne('SELECT @fitbook_code');
    }

    /** Réservations d'un sportif : « a_venir » (confirmées futures) ou « historique » (le reste). */
    public function forUser(User $user, string $period, \DateTimeImmutable $now): QueryBuilder
    {
        $query = $this->createQueryBuilder('r')
            ->join('r.session', 's')->addSelect('s')
            ->leftJoin('s.gym', 'g')->addSelect('g')
            ->leftJoin('s.activity', 'a')->addSelect('a')
            ->andWhere('r.user = :user')->setParameter('user', $user);

        if ($period === 'a_venir') {
            return $query->andWhere('r.status = :confirmed AND s.startAt > :now')
                ->setParameter('confirmed', ReservationStatus::Confirmee)
                ->setParameter('now', $now)
                ->orderBy('s.startAt', 'ASC');
        }

        return $query->andWhere('r.status <> :confirmed OR s.startAt <= :now')
            ->setParameter('confirmed', ReservationStatus::Confirmee)
            ->setParameter('now', $now)
            ->orderBy('s.startAt', 'DESC');
    }

    /**
     * Passe à « terminée » les réservations confirmées des séances actives finies : c'est ce
     * statut qui ouvre le droit de laisser un avis (RG-06). Une séance annulée n'a pas eu lieu.
     *
     * @return int nombre de réservations mises à jour
     */
    public function markFinished(\DateTimeImmutable $now): int
    {
        $ids = $this->createQueryBuilder('r')
            ->select('r.id')
            ->join('r.session', 's')
            ->andWhere('r.status = :confirmed AND s.endAt <= :now AND s.status = :active')
            ->setParameter('confirmed', ReservationStatus::Confirmee)
            ->setParameter('now', $now)
            ->setParameter('active', SessionStatus::Active)
            ->getQuery()->getSingleColumnResult();

        if ($ids === []) {
            return 0;
        }

        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.status', ':finished')
            ->where('r.id IN (:ids)')
            ->setParameter('finished', ReservationStatus::Terminee)
            ->setParameter('ids', $ids)
            ->getQuery()->execute();
    }

    /** Nouvel horaire de la séance : chaque inscrit recevra un nouveau rappel (app:send-reminders). */
    public function resetReminders(Session $session): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.reminderSentAt', 'NULL')
            ->where('r.session = :session')
            ->setParameter('session', $session)
            ->getQuery()->execute();
    }

    /**
     * Marque le rappel comme envoyé, seulement s'il ne l'était pas encore (mise à jour atomique) :
     * renvoie faux si un autre passage de la commande l'a déjà pris en charge (aucun double envoi).
     */
    public function claimReminder(Reservation $reservation, \DateTimeImmutable $sentAt): bool
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.reminderSentAt', ':sentAt')
            ->where('r.id = :id AND r.reminderSentAt IS NULL')
            ->setParameter('sentAt', $sentAt)
            ->setParameter('id', $reservation->getId())
            ->getQuery()->execute() > 0;
    }

    /** Envoi du rappel échoué : la marque est retirée pour qu'il soit retenté. */
    public function releaseReminder(Reservation $reservation): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.reminderSentAt', 'NULL')
            ->where('r.id = :id')
            ->setParameter('id', $reservation->getId())
            ->getQuery()->execute();
    }

    /**
     * Enregistre le jeton du QR code par une requête directe : un échec (collision du jeton
     * unique, coupure) ne ferme pas l'EntityManager et n'annule pas la réservation.
     */
    public function storeQrToken(int $reservationId, string $token): void
    {
        $this->getEntityManager()->getConnection()
            ->executeStatement('UPDATE reservation SET qr_token = ? WHERE id = ?', [$token, $reservationId]);
    }

    /**
     * Réservations vues par un organisateur : celles des séances d'une salle, d'un coach ou
     * de toutes les salles d'un gestionnaire (aucun filtre : toutes, pour l'administrateur).
     */
    public function forOrganizer(?Gym $gym = null, ?CoachProfile $coach = null, ?User $manager = null, bool $upcomingOnly = false, ?\DateTimeImmutable $now = null, ?string $status = null): QueryBuilder
    {
        $query = $this->createQueryBuilder('r')
            ->join('r.user', 'u')->addSelect('u')
            ->join('r.session', 's')->addSelect('s')
            ->leftJoin('s.gym', 'g')->addSelect('g')
            ->leftJoin('s.activity', 'a')->addSelect('a')
            ->leftJoin('s.coach', 'c')->addSelect('c')
            ->leftJoin('c.user', 'cu')->addSelect('cu');

        if ($gym !== null) {
            $query->andWhere('s.gym = :gym')->setParameter('gym', $gym);
        }
        if ($coach !== null) {
            $query->andWhere('s.coach = :coach')->setParameter('coach', $coach);
        }
        if ($manager !== null) {
            $query->andWhere('g.manager = :manager')->setParameter('manager', $manager);
        }
        if ($status !== null && ReservationStatus::tryFrom($status) !== null) {
            $query->andWhere('r.status = :status')->setParameter('status', ReservationStatus::from($status));
        }
        if ($upcomingOnly) {
            return $query->andWhere('s.startAt > :now')->setParameter('now', $now ?? new \DateTimeImmutable())
                ->orderBy('s.startAt', 'ASC')->addOrderBy('u.lastName', 'ASC');
        }

        return $query->orderBy('s.startAt', 'DESC')->addOrderBy('u.lastName', 'ASC');
    }

    /**
     * Réservations (confirmées ou terminées) par semaine de séance, pour le graphique du
     * tableau de bord : 8 semaines passées et 4 à venir, semaines sans réservation comprises.
     *
     * @return list<array{semaine: string, reservations: int}> « semaine » = lundi au format AAAA-MM-JJ
     */
    public function weeklyCounts(?Gym $gym, \DateTimeImmutable $now, ?CoachProfile $coach = null, ?User $manager = null): array
    {
        $timezone = new \DateTimeZone('Europe/Brussels');
        $firstMonday = $now->setTimezone($timezone)->modify('monday this week')->setTime(0, 0)->modify('-8 weeks');
        $end = $firstMonday->modify('+12 weeks');

        $query = $this->createQueryBuilder('r')
            ->select('s.startAt AS startAt')
            ->join('r.session', 's')
            ->leftJoin('s.gym', 'g')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', [ReservationStatus::Confirmee, ReservationStatus::Terminee])
            ->andWhere('s.startAt >= :from AND s.startAt < :to')
            ->setParameter('from', $firstMonday->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('to', $end->setTimezone(new \DateTimeZone('UTC')));
        if ($gym !== null) {
            $query->andWhere('s.gym = :gym')->setParameter('gym', $gym);
        }
        if ($coach !== null) {
            $query->andWhere('s.coach = :coach')->setParameter('coach', $coach);
        }
        if ($manager !== null) {
            $query->andWhere('g.manager = :manager')->setParameter('manager', $manager);
        }

        $weeks = [];
        for ($week = $firstMonday; $week < $end; $week = $week->modify('+1 week')) {
            $weeks[$week->format('Y-m-d')] = 0;
        }
        foreach ($query->getQuery()->getArrayResult() as $row) {
            /** @var \DateTimeImmutable $start */
            $start = $row['startAt'];
            $monday = $start->setTimezone($timezone)->modify('monday this week')->format('Y-m-d');
            if (isset($weeks[$monday])) {
                ++$weeks[$monday];
            }
        }

        return array_map(static fn (string $w, int $n): array => ['semaine' => $w, 'reservations' => $n], array_keys($weeks), $weeks);
    }

    /** @return array<string, int> statut => nombre de réservations */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.status AS status, COUNT(r.id) AS n')
            ->groupBy('r.status')
            ->getQuery()->getArrayResult();

        $counts = array_fill_keys(array_map(static fn (ReservationStatus $s): string => $s->value, ReservationStatus::cases()), 0);
        foreach ($rows as $row) {
            $status = $row['status'] instanceof ReservationStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Réservations confirmées dont la séance commence dans la fenêtre donnée et qui n'ont pas
     * encore reçu de rappel (commande app:send-reminders).
     *
     * @return list<Reservation>
     */
    public function dueForReminder(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.session', 's')->addSelect('s')
            ->join('r.user', 'u')->addSelect('u')
            ->leftJoin('s.gym', 'g')->addSelect('g')
            ->andWhere('r.status = :confirmed AND r.reminderSentAt IS NULL')
            ->andWhere('s.startAt > :from AND s.startAt <= :to')
            ->setParameter('confirmed', ReservationStatus::Confirmee)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getResult();
    }

    /** Le sportif a-t-il une réservation terminée liée à cette salle, ce coach ou cette séance ? (RG-06) */
    public function hasFinishedReservationFor(User $user, Gym|CoachProfile|Session $target): bool
    {
        $query = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.session', 's')
            ->andWhere('r.user = :user AND r.status = :finished')
            ->setParameter('user', $user)
            ->setParameter('finished', ReservationStatus::Terminee);

        $field = match (true) {
            $target instanceof Gym => 's.gym',
            $target instanceof CoachProfile => 's.coach',
            default => 's',
        };

        return (int) $query->andWhere("{$field} = :target")->setParameter('target', $target)->getQuery()->getSingleScalarResult() > 0;
    }
}
