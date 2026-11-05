<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Review;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un avis : son auteur le modifie ou le supprime ; seul l'administrateur le modère
 * (valider / masquer, RG-08).
 *
 * @extends Voter<string, Review>
 */
final class ReviewVoter extends Voter
{
    public const EDIT = 'REVIEW_EDIT';
    public const DELETE = 'REVIEW_DELETE';
    public const MODERATE = 'REVIEW_MODERATE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE, self::MODERATE], true) && $subject instanceof Review;
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

        return $attribute !== self::MODERATE && $subject->getAuthor() === $user;
    }
}
