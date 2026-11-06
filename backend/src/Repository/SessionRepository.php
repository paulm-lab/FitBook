<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\SearchCriteria;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        $query = $this->publicOnly($this->withRelations($this->createQueryBuilder('s')))
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)
            ->andWhere('s.startAt > :now')->setParameter('now', $now)
            ->orderBy('s.startAt', 'ASC');

        if ($activity !== null && $activity !== '') {
            $query->andWhere('LOWER(a.name) LIKE :activity')
                ->setParameter('activity', self::like($activity));
        }
        if ($place !== null && $place !== '') {
            $query->andWhere('LOWER(g.name) LIKE :place OR LOWER(g.address) LIKE :place OR LOWER(g.city) LIKE :place')
                ->setParameter('place', self::like($place));
        }
        // Jour local (fuseau de $day, Bruxelles) converti en bornes UTC, comme les dates stockées.
        if ($day !== null) {
            $query->andWhere('s.startAt >= :dayStart AND s.startAt < :dayEnd')
                ->setParameter('dayStart', self::utc($day->setTime(0, 0)))
                ->setParameter('dayEnd', self::utc($day->setTime(0, 0)->modify('+1 day')));
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Recherche avancée des séances actives à venir. Les filtres simples sont appliqués en SQL ;
     * la note et la distance, calculées, sont appliquées ensuite par SearchService.
     */
    public function search(SearchCriteria $criteria, \DateTimeImmutable $now): QueryBuilder
    {
        $query = $this->publicOnly($this->withRelations($this->createQueryBuilder('s')))
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)
            ->andWhere('s.startAt > :now')->setParameter('now', $now);

        if ($criteria->q !== '') {
            $query->andWhere('LOWER(s.title) LIKE :q OR LOWER(a.name) LIKE :q OR LOWER(g.name) LIKE :q OR LOWER(cu.lastName) LIKE :q OR LOWER(cu.firstName) LIKE :q')
                ->setParameter('q', self::like($criteria->q));
        }
        if ($criteria->ville !== '') {
            $query->andWhere('LOWER(g.city) LIKE :ville OR g.postalCode = :cp OR LOWER(c.city) LIKE :ville')
                ->setParameter('ville', self::like($criteria->ville))
                ->setParameter('cp', $criteria->ville);
        }
        if ($criteria->activiteId !== null) {
            $query->andWhere('a.id = :activityId')->setParameter('activityId', $criteria->activiteId);
        } elseif ($criteria->activite !== '') {
            $query->andWhere('LOWER(a.name) LIKE :activity')->setParameter('activity', self::like($criteria->activite));
        }
        // Dates locales (Bruxelles) converties en UTC, comme les dates stockées.
        if ($criteria->du !== null) {
            $query->andWhere('s.startAt >= :from')->setParameter('from', self::utc($criteria->du));
        }
        if ($criteria->au !== null) {
            $query->andWhere('s.startAt < :to')->setParameter('to', self::utc($criteria->au->modify('+1 day')));
        }
        if ($criteria->prixMax !== null) {
            $query->andWhere('s.price <= :maxPrice')->setParameter('maxPrice', $criteria->prixMax);
        }
        if ($criteria->niveau !== null) {
            $query->andWhere('s.level = :level')->setParameter('level', $criteria->niveau);
        }

        return match ($criteria->tri) {
            'prix' => $query->orderBy('s.price', 'ASC')->addOrderBy('s.startAt', 'ASC'),
            'nom' => $query->orderBy('s.title', 'ASC')->addOrderBy('s.startAt', 'ASC'),
            default => $query->orderBy('s.startAt', 'ASC'),
        };
    }

    /**
     * Prochaines séances actives d'une salle ou d'un coach (fiches publiques).
     *
     * @return list<Session>
     */
    public function upcomingFor(Gym|CoachProfile $owner, \DateTimeImmutable $now, int $limit = 20): array
    {
        return $this->publicOnly($this->withRelations($this->createQueryBuilder('s')))
            ->andWhere($owner instanceof Gym ? 's.gym = :owner' : 's.coach = :owner')->setParameter('owner', $owner)
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)
            ->andWhere('s.startAt > :now')->setParameter('now', $now)
            ->orderBy('s.startAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Séances gérées par un compte : celles qu'il anime (coach) et celles de ses salles
     * (gestionnaire). Toutes les séances pour l'administrateur ($owner = null).
     *
     * @param string|null $period « a_venir », « passees » ou null (toutes)
     */
    public function managedBy(?User $owner, ?string $period, \DateTimeImmutable $now, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): QueryBuilder
    {
        $query = $this->withRelations($this->createQueryBuilder('s'));

        if ($owner !== null) {
            $query->andWhere('cu = :owner OR g.manager = :owner')->setParameter('owner', $owner);
        }
        if ($period === 'a_venir') {
            $query->andWhere('s.startAt > :now')->setParameter('now', $now)->orderBy('s.startAt', 'ASC');
        } elseif ($period === 'passees') {
            $query->andWhere('s.startAt <= :now')->setParameter('now', $now)->orderBy('s.startAt', 'DESC');
        } else {
            $query->orderBy('s.startAt', 'ASC');
        }
        // Fenêtre du calendrier (FullCalendar envoie le début et la fin de la vue).
        if ($from !== null) {
            $query->andWhere('s.endAt >= :from')->setParameter('from', self::utc($from));
        }
        if ($to !== null) {
            $query->andWhere('s.startAt < :to')->setParameter('to', self::utc($to));
        }

        return $query;
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

    /**
     * Ce qui empêche de supprimer une séance sans perdre d'historique : réservations non
     * annulées (confirmées ou terminées) et avis.
     *
     * @return array{reservations: int, reviews: int}
     */
    public function historyCounts(Session $session): array
    {
        $em = $this->getEntityManager();

        return [
            'reservations' => (int) $em->createQueryBuilder()
                ->select('COUNT(r.id)')->from(Reservation::class, 'r')
                ->andWhere('r.session = :session')->setParameter('session', $session)
                ->andWhere('r.status <> :cancelled')->setParameter('cancelled', ReservationStatus::Annulee)
                ->getQuery()->getSingleScalarResult(),
            'reviews' => (int) $em->createQueryBuilder()
                ->select('COUNT(v.id)')->from(Review::class, 'v')
                ->andWhere('v.session = :session')->setParameter('session', $session)
                ->getQuery()->getSingleScalarResult(),
        ];
    }

    /** Nombre de séances actives à venir animées par ce coach. */
    public function countUpcomingFor(CoachProfile $coach, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.coach = :coach')->setParameter('coach', $coach)
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)
            ->andWhere('s.startAt > :now')->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult();
    }

    /** Motif LIKE insensible à la casse ; les jokers % et _ saisis par l'utilisateur sont neutralisés. */
    public static function like(string $text): string
    {
        return '%' . addcslashes(mb_strtolower($text), '%_') . '%';
    }

    private function withRelations(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->leftJoin('s.activity', 'a')->addSelect('a')
            ->leftJoin('s.coach', 'c')->addSelect('c')
            ->leftJoin('c.user', 'cu')->addSelect('cu')
            ->leftJoin('s.gym', 'g')->addSelect('g');
    }

    /**
     * Catalogue public : les séances d'un coach bloqué (ou non vérifié) sont masquées,
     * comme son profil (voir Session::hasAvailableCoach()).
     */
    private function publicOnly(QueryBuilder $query): QueryBuilder
    {
        return $query->andWhere('c.id IS NULL OR (cu.isVerified = true AND cu.isBlocked = false)');
    }

    private static function utc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
