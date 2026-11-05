<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Security\Voter\GymVoter;
use App\Security\Voter\ReservationVoter;
use App\Security\Voter\ReviewVoter;
use App\Security\Voter\SessionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Propriété des ressources (RG-11) : chacun n'agit que sur ce qui lui appartient,
 * l'administrateur agit sur tout.
 */
final class VotersTest extends TestCase
{
    private User $admin;
    private User $coach;
    private User $otherCoach;
    private User $manager;
    private User $otherManager;
    private User $athlete;
    private User $otherAthlete;

    protected function setUp(): void
    {
        $this->admin = $this->user(User::ROLE_ADMIN);
        $this->coach = $this->user(User::ROLE_COACH);
        $this->otherCoach = $this->user(User::ROLE_COACH);
        $this->manager = $this->user(User::ROLE_GESTIONNAIRE);
        $this->otherManager = $this->user(User::ROLE_GESTIONNAIRE);
        $this->athlete = $this->user(User::ROLE_SPORTIF);
        $this->otherAthlete = $this->user(User::ROLE_SPORTIF);
        (new CoachProfile())->setUser($this->coach);
        (new CoachProfile())->setUser($this->otherCoach);
    }

    public function testCoachEditsOnlyTheSessionsHeTeaches(): void
    {
        $session = (new Session())->setCoach($this->coach->getCoachProfile());
        $voter = $this->sessionVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->coach, $session, SessionVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->otherCoach, $session, SessionVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->athlete, $session, SessionVoter::DELETE));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->admin, $session, SessionVoter::DELETE));
    }

    public function testManagerEditsOnlyTheSessionsAndGymsHeManages(): void
    {
        $gym = (new Gym())->setManager($this->manager);
        $session = (new Session())->setGym($gym);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->sessionVoter(), $this->manager, $session, SessionVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->sessionVoter(), $this->otherManager, $session, SessionVoter::EDIT));

        $gymVoter = new GymVoter($this->decisions());
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($gymVoter, $this->manager, $gym, GymVoter::VIEW_STATS));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($gymVoter, $this->otherManager, $gym, GymVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($gymVoter, $this->coach, $gym, GymVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($gymVoter, $this->admin, $gym, GymVoter::DELETE));
    }

    public function testRoleIsRequiredInAdditionToOwnership(): void
    {
        // Un ancien gestionnaire devenu sportif ne gère plus sa salle.
        $gym = (new Gym())->setManager($this->manager);
        $this->manager->setRoles([User::ROLE_SPORTIF]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new GymVoter($this->decisions()), $this->manager, $gym, GymVoter::EDIT));
    }

    public function testOnlyTheAthleteCancelsHisReservationButTheCoachCanSeeIt(): void
    {
        $session = (new Session())->setCoach($this->coach->getCoachProfile());
        $reservation = (new Reservation())->setUser($this->athlete)->setSession($session);
        $voter = new ReservationVoter($this->decisions(), $this->sessionVoter());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->athlete, $reservation, ReservationVoter::CANCEL));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->otherAthlete, $reservation, ReservationVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->coach, $reservation, ReservationVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->coach, $reservation, ReservationVoter::CANCEL));
    }

    public function testOnlyTheAuthorEditsAReviewAndOnlyTheAdminModerates(): void
    {
        $review = (new Review())->setAuthor($this->athlete);
        $voter = new ReviewVoter($this->decisions());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->athlete, $review, ReviewVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->athlete, $review, ReviewVoter::MODERATE));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $this->otherAthlete, $review, ReviewVoter::DELETE));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $this->admin, $review, ReviewVoter::MODERATE));
    }

    public function testVotersAbstainOnUnsupportedAttributes(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->sessionVoter(), $this->admin, new Gym(), SessionVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new GymVoter($this->decisions()), $this->admin, new Gym(), 'AUTRE'));
    }

    private function sessionVoter(): SessionVoter
    {
        return new SessionVoter($this->decisions());
    }

    /** Gestionnaire de décision simulé : seul ROLE_ADMIN est accordé, à qui le possède. */
    private function decisions(): AccessDecisionManagerInterface
    {
        $decisions = $this->createStub(AccessDecisionManagerInterface::class);
        $decisions->method('decide')->willReturnCallback(
            static fn ($token, array $attributes): bool => $attributes === [User::ROLE_ADMIN]
                && \in_array(User::ROLE_ADMIN, $token->getRoleNames(), true),
        );

        return $decisions;
    }

    private function vote(VoterInterface $voter, User $user, mixed $subject, string $attribute): int
    {
        return $voter->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $subject, [$attribute]);
    }

    private function user(string $role): User
    {
        static $n = 0;

        return (new User())->setEmail('u' . ++$n . '@example.test')->setRoles([$role]);
    }
}
