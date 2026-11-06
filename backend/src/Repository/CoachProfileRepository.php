<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\SearchCriteria;
use App\Entity\CoachProfile;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : profils des coachs.
 *
 * Seuls les coachs dont le compte est vérifié et non bloqué sont publiés.
 *
 * @extends ServiceEntityRepository<CoachProfile>
 */
class CoachProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoachProfile::class);
    }

    /**
     * Coachs publiés (compte vérifié et non bloqué, rôle coach toujours attribué), triés par nom.
     * Le profil d'un compte qui a changé de rôle est conservé mais n'est plus publié.
     */
    public function published(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->join('c.user', 'u')->addSelect('u')
            ->andWhere('u.isVerified = true AND u.isBlocked = false')
            // Rôles stockés en JSON (une seule valeur par compte) : recherche textuelle.
            ->andWhere('u.roles LIKE :coachRole')->setParameter('coachRole', '%"' . User::ROLE_COACH . '"%')
            ->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');
    }

    /**
     * Recherche des coachs : texte (nom, bio, spécialités), ville, activité animée, tarif maximum.
     * Les spécialités sont stockées en JSON : la recherche textuelle porte sur leur texte brut.
     */
    public function search(SearchCriteria $criteria): QueryBuilder
    {
        $query = $this->published();

        if ($criteria->q !== '') {
            $query->andWhere('LOWER(u.lastName) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(c.bio) LIKE :q OR LOWER(c.specialities) LIKE :q')
                ->setParameter('q', SessionRepository::like($criteria->q));
        }
        if ($criteria->ville !== '') {
            $query->andWhere('LOWER(c.city) LIKE :ville')->setParameter('ville', SessionRepository::like($criteria->ville));
        }
        if ($criteria->prixMax !== null) {
            $query->andWhere('c.hourlyRate <= :maxRate')->setParameter('maxRate', $criteria->prixMax);
        }
        if ($criteria->activiteId !== null || $criteria->activite !== '') {
            // Coachs qui animent au moins une séance de cette activité, ou qui l'ont en spécialité.
            $sub = $this->getEntityManager()->createQueryBuilder()
                ->select('1')->from(Session::class, 'cs')->join('cs.activity', 'ca')
                ->andWhere('cs.coach = c');
            if ($criteria->activiteId !== null) {
                $sub->andWhere('ca.id = :activityId');
                $query->andWhere($query->expr()->exists($sub->getDQL()))->setParameter('activityId', $criteria->activiteId);
            } else {
                $sub->andWhere('LOWER(ca.name) LIKE :activity');
                $query->andWhere($query->expr()->orX(
                    $query->expr()->exists($sub->getDQL()),
                    'LOWER(c.specialities) LIKE :activity',
                ))->setParameter('activity', SessionRepository::like($criteria->activite));
            }
        }

        return $query;
    }
}
