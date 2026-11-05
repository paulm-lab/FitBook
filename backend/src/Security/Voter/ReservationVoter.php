<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une réservation : le sportif qui l'a faite la consulte et l'annule ;
 * le coach ou le gestionnaire de la séance la consulte ; l'administrateur peut tout faire.
 *
 * @extends Voter<string, Reservation>
 */
final class ReservationVoter extends Voter
{
    public const VIEW = 'RESERVATION_VIEW';
    public const CANCEL = 'RESERVATION_CANCEL';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly SessionVoter $sessionVoter,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::CANCEL], true) && $subject instanceof Reservation;
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
        if ($subject->getUser() === $user) {
            return true;
        }

        // Le responsable de la séance voit la réservation, mais ne l'annule pas à la place du sportif.
        $session = $subject->getSession();

        return $attribute === self::VIEW
            && $session !== null
            && $this->sessionVoter->vote($token, $session, [SessionVoter::VIEW_RESERVATIONS]) === self::ACCESS_GRANTED;
    }
}
