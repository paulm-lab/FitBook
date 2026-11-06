<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion d'un compte bloqué ou non vérifié (RG-02).
 */
final class UserChecker implements UserCheckerInterface
{
    /** Message (clé de traduction) d'un compte dont l'adresse n'est pas encore confirmée. */
    public const UNVERIFIED_MESSAGE = 'Confirmez votre adresse e-mail avant de vous connecter.';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    // Vérifié après le mot de passe : l'état du compte n'est révélé qu'à son propriétaire.
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if ($user->isBlocked()) {
            throw new CustomUserMessageAccountStatusException('Ce compte a été bloqué. Contactez l’administrateur.');
        }
        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(self::UNVERIFIED_MESSAGE);
        }
    }
}
