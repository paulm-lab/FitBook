<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'une réservation : confirmée → annulée ou terminée.
 */
enum ReservationStatus: string
{
    case Confirmee = 'confirmee';
    case Annulee = 'annulee';
    case Terminee = 'terminee';
}
