<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\SearchCriteria;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : salles de sport, dont l'appel à la procédure stockée sp_stats_salle.
 *
 * @extends ServiceEntityRepository<Gym>
 */
class GymRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gym::class);
    }

    /** Recherche des salles par texte, ville ou code postal, et activité proposée. */
    public function search(SearchCriteria $criteria): QueryBuilder
    {
        $query = $this->createQueryBuilder('g');

        if ($criteria->q !== '') {
            $query->andWhere('LOWER(g.name) LIKE :q OR LOWER(g.description) LIKE :q OR LOWER(g.address) LIKE :q')
                ->setParameter('q', SessionRepository::like($criteria->q));
        }
        if ($criteria->ville !== '') {
            $query->andWhere('LOWER(g.city) LIKE :ville OR g.postalCode = :cp')
                ->setParameter('ville', SessionRepository::like($criteria->ville))
                ->setParameter('cp', $criteria->ville);
        }
        if ($criteria->activiteId !== null || $criteria->activite !== '') {
            // Salles qui proposent au moins une séance de cette activité.
            $sub = $this->getEntityManager()->createQueryBuilder()
                ->select('1')->from(Session::class, 'gs')->join('gs.activity', 'ga')
                ->andWhere('gs.gym = g');
            if ($criteria->activiteId !== null) {
                $sub->andWhere('ga.id = :activityId');
                $query->setParameter('activityId', $criteria->activiteId);
            } else {
                $sub->andWhere('LOWER(ga.name) LIKE :activity');
                $query->setParameter('activity', SessionRepository::like($criteria->activite));
            }
            $query->andWhere($query->expr()->exists($sub->getDQL()));
        }

        return $query->orderBy('g.name', 'ASC');
    }

    /**
     * Statistiques du tableau de bord gestionnaire (US-G05).
     *
     * Sur MySQL : CALL sp_stats_salle. Sur la base SQLite des tests, les mêmes chiffres sont
     * calculés en DQL (mêmes règles : séances actives, réservations confirmées ou terminées,
     * avis visibles).
     *
     * @return array{nbSeances: int, capaciteTotale: int, nbReservations: int, tauxRemplissage: float|null, noteMoyenne: float|null, nbAvis: int}
     */
    public function stats(Gym $gym): array
    {
        $connection = $this->getEntityManager()->getConnection();

        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $row = $connection->fetchAssociative('CALL sp_stats_salle(?)', [$gym->getId()]);

            return $this->format(
                (int) $row['nb_seances'],
                (int) $row['capacite_totale'],
                (int) $row['nb_reservations'],
                $row['note_moyenne'] === null ? null : (float) $row['note_moyenne'],
                (int) $row['nb_avis'],
            );
        }

        $em = $this->getEntityManager();
        [$sessions, $capacity] = array_values($em->createQueryBuilder()
            ->select('COUNT(s.id), COALESCE(SUM(s.capacity), 0)')
            ->from(Session::class, 's')
            ->andWhere('s.gym = :gym AND s.status = :active')
            ->setParameter('gym', $gym)->setParameter('active', SessionStatus::Active)
            ->getQuery()->getSingleResult());
        $reservations = $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')->join('r.session', 's')
            ->andWhere('s.gym = :gym AND s.status = :active AND r.status IN (:statuses)')
            ->setParameter('gym', $gym)->setParameter('active', SessionStatus::Active)
            ->setParameter('statuses', [ReservationStatus::Confirmee, ReservationStatus::Terminee])
            ->getQuery()->getSingleScalarResult();
        [$rating, $reviews] = array_values($em->createQueryBuilder()
            ->select('AVG(v.rating), COUNT(v.id)')
            ->from(Review::class, 'v')
            ->andWhere('v.gym = :gym AND v.isVisible = true')
            ->setParameter('gym', $gym)
            ->getQuery()->getSingleResult());

        return $this->format((int) $sessions, (int) $capacity, (int) $reservations, $rating === null ? null : round((float) $rating, 2), (int) $reviews);
    }

    /** @return array{nbSeances: int, capaciteTotale: int, nbReservations: int, tauxRemplissage: float|null, noteMoyenne: float|null, nbAvis: int} */
    private function format(int $sessions, int $capacity, int $reservations, ?float $rating, int $reviews): array
    {
        return [
            'nbSeances' => $sessions,
            'capaciteTotale' => $capacity,
            'nbReservations' => $reservations,
            'tauxRemplissage' => $capacity > 0 ? round(100 * $reservations / $capacity, 1) : null,
            'noteMoyenne' => $rating,
            'nbAvis' => $reviews,
        ];
    }
}
