<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État d'une séance : une séance annulée ne peut plus être réservée (RG-12).
 */
enum SessionStatus: string
{
    case Active = 'active';
    case Annulee = 'annulee';
}
