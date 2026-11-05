<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Représentation JSON d'un compte, partagée par la connexion, /api/me et le profil.
 *
 * Les clés restent en français (nom, prenom, telephone) : c'est le contrat attendu par le
 * front Angular actuel, qui sera revu avec la refonte du frontend (Phase 5).
 * Le mot de passe n'est jamais exposé.
 */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        return [
            'id' => $user->getId(),
            'nom' => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email' => $user->getEmail(),
            'telephone' => $user->getPhone(),
            'roles' => $user->getRoles(),
        ];
    }
}
