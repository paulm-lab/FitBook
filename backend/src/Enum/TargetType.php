<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de cible d'un favori : une salle, un coach ou une séance.
 */
enum TargetType: string
{
    case Gym = 'gym';
    case Coach = 'coach';
    case Session = 'session';
}
