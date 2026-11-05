<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Session;
use App\Http\ApiException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cible d'un favori ou d'un avis, désignée dans l'API par { "type": "salle|coach|seance", "cibleId": 3 }.
 */
final class TargetResolver
{
    public const TYPES = [
        'salle' => Gym::class,
        'coach' => CoachProfile::class,
        'seance' => Session::class,
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @throws ApiException 422 si le type est inconnu, 404 si la cible n'existe pas */
    public function resolve(mixed $type, mixed $id): Gym|CoachProfile|Session
    {
        if (!\is_string($type) || !isset(self::TYPES[$type])) {
            throw ApiException::validation(['type' => 'Type attendu : salle, coach ou seance.']);
        }
        if (!\is_int($id) && !(\is_string($id) && ctype_digit($id))) {
            throw ApiException::validation(['cibleId' => 'L’identifiant de la cible est invalide.']);
        }

        /** @var Gym|CoachProfile|Session|null $target */
        $target = $this->em->find(self::TYPES[$type], (int) $id);

        return $target ?? throw ApiException::notFound('Cible introuvable.');
    }
}
