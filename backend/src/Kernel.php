<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Toutes les dates sont manipulées et stockées en UTC, quel que soit le php.ini du poste :
     * les procédures stockées comparent avec UTC_TIMESTAMP(), et l'API renvoie des dates
     * ISO 8601 avec décalage, que le front convertit dans le fuseau du navigateur.
     */
    public function boot(): void
    {
        date_default_timezone_set('UTC');

        parent::boot();
    }
}
