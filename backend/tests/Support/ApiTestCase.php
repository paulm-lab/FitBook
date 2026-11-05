<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Activity;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionLevel;
use App\Enum\SessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels de l'API : base SQLite en mémoire recréée à chaque test,
 * petites fabriques d'entités et lecture des réponses JSON.
 *
 * Les entités sont rechargées par leur identifiant avant usage : chaque requête du client
 * vide l'EntityManager.
 */
abstract class ApiTestCase extends WebTestCase
{
    use JwtLoginTrait;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = $this->em();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function login(User $user): void
    {
        $this->loginWithJwt($this->client, $this->reload($user));
    }

    protected function logout(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
    }

    protected function user(string $email, string $role = User::ROLE_SPORTIF, string $firstName = 'Test', string $lastName = 'Utilisateur'): User
    {
        $user = (new User())->setEmail($email)->setFirstName($firstName)->setLastName($lastName)
            ->setPassword('test-hash')->setRoles([$role])->setIsVerified(true);
        if ($role === User::ROLE_COACH) {
            $user->setCoachProfile((new CoachProfile())->setCity('Ixelles')->setSpecialities(['Yoga'])->setHourlyRate('40.00'));
            $this->em()->persist($user->getCoachProfile());
        }
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function activity(string $name = 'Yoga'): Activity
    {
        $activity = (new Activity())->setName($name)->setCategory('Bien-être');
        $this->em()->persist($activity);
        $this->em()->flush();

        return $activity;
    }

    protected function gym(User $manager, string $name = 'Salle test', string $city = 'Bruxelles', ?string $lat = '50.850000', ?string $lng = '4.350000'): Gym
    {
        $gym = (new Gym())->setManager($this->reload($manager))->setName($name)->setAddress('Rue du Test 1')
            ->setPostalCode('1000')->setCity($city)->setLatitude($lat)->setLongitude($lng);
        $this->em()->persist($gym);
        $this->em()->flush();

        return $gym;
    }

    protected function session(
        Activity $activity,
        ?Gym $gym,
        ?CoachProfile $coach,
        string $start = '+2 days',
        int $capacity = 10,
        string $price = '10.00',
        SessionStatus $status = SessionStatus::Active,
        SessionLevel $level = SessionLevel::Debutant,
        string $title = 'Séance test',
    ): Session {
        $startAt = new \DateTimeImmutable($start);
        $session = (new Session())->setActivity($this->reload($activity))
            ->setGym($gym ? $this->reload($gym) : null)->setCoach($coach ? $this->reload($coach) : null)
            ->setTitle($title)->setStartAt($startAt)->setEndAt($startAt->modify('+1 hour'))
            ->setCapacity($capacity)->setPrice($price)->setLevel($level)->setStatus($status);
        $this->em()->persist($session);
        $this->em()->flush();

        return $session;
    }

    protected function reservation(User $user, Session $session, ReservationStatus $status = ReservationStatus::Confirmee): Reservation
    {
        $reservation = (new Reservation())->setUser($this->reload($user))->setSession($this->reload($session))->setStatus($status);
        $this->em()->persist($reservation);
        $this->em()->flush();

        return $reservation;
    }

    protected function review(User $author, Gym|CoachProfile|Session $target, int $rating, bool $visible = true): Review
    {
        $review = (new Review())->setAuthor($this->reload($author))->setTarget($this->reload($target))
            ->setRating($rating)->setIsVisible($visible);
        $this->em()->persist($review);
        $this->em()->flush();

        return $review;
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected function reload(object $entity): object
    {
        return $this->em()->find($entity::class, $entity->getId());
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @param array<mixed> $body */
    protected function send(string $method, string $uri, array $body = []): void
    {
        $this->client->jsonRequest($method, $uri, $body);
    }
}
