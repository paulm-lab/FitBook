<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : avis.
 *
 * Seuls les avis visibles (non masqués par la modération) sont publiés et comptent dans
 * la note moyenne (RG-08).
 *
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    /** Champ de la cible dans la table review, par type d'entité. */
    private const TARGET_FIELDS = [
        Gym::class => 'gym',
        CoachProfile::class => 'coach',
        Session::class => 'session',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** Avis visibles d'une cible, du plus récent au plus ancien. */
    public function visibleFor(Gym|CoachProfile|Session $target): QueryBuilder
    {
        $field = self::TARGET_FIELDS[$target::class];

        return $this->createQueryBuilder('v')
            ->join('v.author', 'a')->addSelect('a')
            ->andWhere("v.{$field} = :target AND v.isVisible = true")
            ->setParameter('target', $target)
            ->orderBy('v.createdAt', 'DESC');
    }

    /**
     * Note moyenne et nombre d'avis visibles de plusieurs cibles du même type, en une requête.
     *
     * @param class-string<Gym|CoachProfile|Session> $class
     * @param list<int>                              $ids
     *
     * @return array<int, array{note: float, nbAvis: int}> id de la cible => résumé
     */
    public function ratings(string $class, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $field = self::TARGET_FIELDS[$class];

        $rows = $this->createQueryBuilder('v')
            ->select("IDENTITY(v.{$field}) AS targetId, AVG(v.rating) AS note, COUNT(v.id) AS nbAvis")
            ->andWhere("v.{$field} IN (:ids) AND v.isVisible = true")
            ->setParameter('ids', $ids)
            ->groupBy("v.{$field}")
            ->getQuery()->getArrayResult();

        $ratings = [];
        foreach ($rows as $row) {
            $ratings[(int) $row['targetId']] = ['note' => round((float) $row['note'], 1), 'nbAvis' => (int) $row['nbAvis']];
        }

        return $ratings;
    }

    /** @return array{note: float|null, nbAvis: int} */
    public function ratingOf(Gym|CoachProfile|Session $target): array
    {
        $rating = $this->ratings($target::class, [(int) $target->getId()])[(int) $target->getId()] ?? null;

        return ['note' => $rating['note'] ?? null, 'nbAvis' => $rating['nbAvis'] ?? 0];
    }

    public function findOneByAuthorAndTarget(User $author, Gym|CoachProfile|Session $target): ?Review
    {
        return $this->findOneBy(['author' => $author, self::TARGET_FIELDS[$target::class] => $target]);
    }

    /**
     * Liste d'administration : tous les avis, filtrables sur « non modérés » ou « masqués ».
     */
    public function forModeration(?string $filter): QueryBuilder
    {
        $query = $this->createQueryBuilder('v')
            ->join('v.author', 'a')->addSelect('a')
            ->leftJoin('v.gym', 'g')->addSelect('g')
            ->leftJoin('v.coach', 'c')->addSelect('c')
            ->leftJoin('c.user', 'cu')->addSelect('cu')
            ->leftJoin('v.session', 's')->addSelect('s')
            ->orderBy('v.createdAt', 'DESC');

        return match ($filter) {
            'non_moderes' => $query->andWhere('v.isModerated = false AND v.isVisible = true'),
            'masques' => $query->andWhere('v.isVisible = false'),
            default => $query,
        };
    }

    /** Avis visibles reçus par les salles d'un gestionnaire ou par un coach (espaces pro). */
    public function receivedBy(?User $manager, ?CoachProfile $coach): QueryBuilder
    {
        $query = $this->createQueryBuilder('v')
            ->join('v.author', 'a')->addSelect('a')
            ->leftJoin('v.gym', 'g')->addSelect('g')
            ->leftJoin('v.session', 's')->addSelect('s')
            ->leftJoin('s.gym', 'sg')
            ->andWhere('v.isVisible = true')
            ->orderBy('v.createdAt', 'DESC');

        if ($manager !== null) {
            return $query->andWhere('g.manager = :manager OR sg.manager = :manager')->setParameter('manager', $manager);
        }

        return $query->andWhere('v.coach = :coach OR s.coach = :coach')->setParameter('coach', $coach);
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.isModerated = false AND v.isVisible = true')
            ->getQuery()->getSingleScalarResult();
    }
}
