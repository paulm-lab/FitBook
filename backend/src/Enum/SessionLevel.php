<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Niveau requis pour suivre une séance.
 */
enum SessionLevel: string
{
    case Debutant = 'debutant';
    case Intermediaire = 'intermediaire';
    case Avance = 'avance';
}
