<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Gym;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une salle (RG-11) : seul son gestionnaire (ou un administrateur) la modifie,
 * consulte ses statistiques ou la supprime.
 *
 * @extends Voter<string, Gym>
 */
final class GymVoter extends Voter
{
    public const EDIT = 'GYM_EDIT';
    public const DELETE = 'GYM_DELETE';
    public const VIEW_STATS = 'GYM_VIEW_STATS';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE, self::VIEW_STATS], true)
            && $subject instanceof Gym;
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

        return $subject->getManager() === $user && $user->hasRole(User::ROLE_GESTIONNAIRE);
    }
}
