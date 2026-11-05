<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoachProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux données : profils des coachs.
 *
 * @extends ServiceEntityRepository<CoachProfile>
 */
class CoachProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoachProfile::class);
    }
}
