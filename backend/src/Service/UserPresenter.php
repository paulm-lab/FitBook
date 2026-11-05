<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Représentation JSON d'un compte, partagée par la connexion, /api/me et le profil.
 *
 * Clés en français (nom, prenom, telephone), comme le reste de l'API.
 * `role` est le rôle principal (sportif, coach, gestionnaire, admin) utilisé par le front
 * pour choisir l'espace à afficher. Le mot de passe n'est jamais exposé.
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
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'role' => $this->mainRole($user),
            'coachId' => $user->getCoachProfile()?->getId(),
        ];
    }

    public function mainRole(User $user): string
    {
        return match (true) {
            $user->hasRole(User::ROLE_ADMIN) => 'admin',
            $user->hasRole(User::ROLE_GESTIONNAIRE) => 'gestionnaire',
            $user->hasRole(User::ROLE_COACH) => 'coach',
            default => 'sportif',
        };
    }
}
