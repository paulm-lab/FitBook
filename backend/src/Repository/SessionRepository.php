<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Session;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : séances.
 *
 * Toutes les requêtes sont paramétrées (aucune concaténation de saisie utilisateur).
 *
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Séances actives à venir, filtrées par activité, lieu (nom, adresse ou ville de la salle) et jour.
     *
     * @return list<Session>
     */
    public function findUpcoming(\DateTimeImmutable $now, ?string $activity = null, ?string $place = null, ?\DateTimeImmutable $day = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->leftJoin('s.activity', 'a')->addSelect('a')
            ->leftJoin('s.coach', 'c')->addSelect('c')
            ->leftJoin('c.user', 'cu')->addSelect('cu')
            ->leftJoin('s.gym', 'g')->addSelect('g')
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)
            ->andWhere('s.startAt > :now')->setParameter('now', $now)
            ->orderBy('s.startAt', 'ASC');

        if ($activity !== null && $activity !== '') {
            $query->andWhere('LOWER(a.name) LIKE :activity')
                ->setParameter('activity', '%' . mb_strtolower($activity) . '%');
        }
        if ($place !== null && $place !== '') {
            $query->andWhere('LOWER(g.name) LIKE :place OR LOWER(g.address) LIKE :place OR LOWER(g.city) LIKE :place')
                ->setParameter('place', '%' . mb_strtolower($place) . '%');
        }
        if ($day !== null) {
            $query->andWhere('s.startAt >= :dayStart AND s.startAt < :dayEnd')
                ->setParameter('dayStart', $day->setTime(0, 0))
                ->setParameter('dayEnd', $day->setTime(0, 0)->modify('+1 day'));
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Nombre de réservations confirmées par séance, en une seule requête.
     *
     * @param list<Session> $sessions
     *
     * @return array<int, int> id de séance => places prises
     */
    public function confirmedCounts(array $sessions): array
    {
        if ($sessions === []) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.session) AS sessionId, COUNT(r.id) AS taken')
            ->from(Reservation::class, 'r')
            ->andWhere('r.session IN (:sessions)')->setParameter('sessions', $sessions)
            ->andWhere('r.status = :confirmed')->setParameter('confirmed', ReservationStatus::Confirmee)
            ->groupBy('r.session')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['sessionId']] = (int) $row['taken'];
        }

        return $counts;
    }
}
