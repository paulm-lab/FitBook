<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Session;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une séance (RG-11) : un coach ne modifie que les séances qu'il anime,
 * un gestionnaire que celles de ses salles ; l'administrateur peut tout faire.
 *
 * Usage : $this->denyAccessUnlessGranted(SessionVoter::EDIT, $session);
 *
 * @extends Voter<string, Session>
 */
final class SessionVoter extends Voter
{
    public const EDIT = 'SESSION_EDIT';
    public const DELETE = 'SESSION_DELETE';
    /** Voir la liste des inscrits d'une séance. */
    public const VIEW_RESERVATIONS = 'SESSION_VIEW_RESERVATIONS';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE, self::VIEW_RESERVATIONS], true)
            && $subject instanceof Session;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($this->decisions->decide($token, [User::ROLE_ADMIN])) {
            return true;
        }

        // Coach qui anime la séance.
        $coachUser = $subject->getCoach()?->getUser();
        if ($coachUser !== null && $coachUser === $user && $user->hasRole(User::ROLE_COACH)) {
            return true;
        }

        // Gestionnaire de la salle où elle a lieu.
        $manager = $subject->getGym()?->getManager();

        return $manager !== null && $manager === $user && $user->hasRole(User::ROLE_GESTIONNAIRE);
    }
}
