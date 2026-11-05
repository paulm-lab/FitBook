<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CoachProfile;
use App\Entity\Favorite;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Enum\TargetType;
use PHPUnit\Framework\TestCase;

/**
 * Règles de gestion portées par les entités (sans base de données).
 */
final class DomainRulesTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-10 10:00:00');
    }

    public function testUserWithoutRoleIsTreatedAsSportif(): void
    {
        self::assertSame([User::ROLE_SPORTIF], (new User())->getRoles());
        self::assertSame([User::ROLE_COACH], (new User())->setRoles([User::ROLE_COACH, User::ROLE_COACH])->getRoles());
    }

    public function testCoachProfileKeepsBothSidesOfTheRelation(): void
    {
        $user = new User();
        $profile = (new CoachProfile())->setUser($user);

        self::assertSame($profile, $user->getCoachProfile());
    }

    public function testRemainingPlacesIgnoreCancelledReservations(): void
    {
        $session = $this->session('+1 day', capacity: 2);
        $this->reserve($session, ReservationStatus::Confirmee);
        $this->reserve($session, ReservationStatus::Annulee);

        self::assertSame(1, $session->getConfirmedCount());
        self::assertSame(1, $session->getRemainingPlaces());
        self::assertTrue($session->isBookable($this->now));

        $this->reserve($session, ReservationStatus::Confirmee);
        self::assertSame(0, $session->getRemainingPlaces());
        self::assertFalse($session->isBookable($this->now), 'Une séance complète n’est pas réservable (RG-03).');
    }

    public function testPastOrCancelledSessionIsNotBookable(): void
    {
        self::assertFalse($this->session('-1 minute')->isBookable($this->now));
        self::assertFalse($this->session('+1 day')->setStatus(SessionStatus::Annulee)->isBookable($this->now));
    }

    /** RG-05 : annulation possible uniquement plus de 2 heures avant le début. */
    public function testCancellationDeadlineIsTwoHours(): void
    {
        self::assertTrue($this->reserve($this->session('+3 hours'), ReservationStatus::Confirmee)->isCancellable($this->now));
        self::assertFalse($this->reserve($this->session('+2 hours'), ReservationStatus::Confirmee)->isCancellable($this->now));
        self::assertFalse($this->reserve($this->session('+1 hour'), ReservationStatus::Confirmee)->isCancellable($this->now));
        self::assertFalse($this->reserve($this->session('+1 day'), ReservationStatus::Annulee)->isCancellable($this->now));
    }

    public function testFavoriteHasExactlyOneTargetAndMatchingType(): void
    {
        $favorite = (new Favorite())->setTarget(new Gym());
        self::assertSame(TargetType::Gym, $favorite->getType());

        // Changer de cible efface l'ancienne : jamais deux cibles à la fois.
        $session = new Session();
        $favorite->setTarget($session);
        self::assertSame(TargetType::Session, $favorite->getType());
        self::assertNull($favorite->getGym());
        self::assertSame($session, $favorite->getTarget());
    }

    private function session(string $start, int $capacity = 10): Session
    {
        $startAt = $this->now->modify($start);

        return (new Session())->setStartAt($startAt)->setEndAt($startAt->modify('+1 hour'))->setCapacity($capacity);
    }

    private function reserve(Session $session, ReservationStatus $status): Reservation
    {
        $reservation = (new Reservation())->setSession($session)->setStatus($status);
        $session->getReservations()->add($reservation);

        return $reservation;
    }
}
