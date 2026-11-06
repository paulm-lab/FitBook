<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Activity;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionLevel;
use App\Enum\SessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\Support\JwtLoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Catalogue public des séances et création de réservation (1 réservation = 1 place).
 */
final class CatalogueAndReservationTest extends WebTestCase
{
    use JwtLoginTrait;

    private KernelBrowser $client;
    private Gym $gym;
    private Activity $activity;

    protected function setUp(): void
    {
        // Request::create() annonce « Accept-Language: en-us » par défaut : le front envoie toujours la langue.
        $this->client = static::createClient(server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        $this->client->disableReboot();
        $em = $this->em();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $manager = $this->user('gestion@example.test', User::ROLE_GESTIONNAIRE);
        $this->activity = (new Activity())->setName('Yoga')->setCategory('Bien-être');
        $this->gym = (new Gym())->setManager($manager)->setName('Salle test')->setAddress('Rue du Test 1')
            ->setPostalCode('1000')->setCity('Bruxelles');
        $em->persist($this->activity);
        $em->persist($this->gym);
        $em->flush();
    }

    public function testListShowsOnlyActiveUpcomingSessionsWithComputedPlaces(): void
    {
        $upcoming = $this->session('+2 days', capacity: 3);
        $this->session('-2 days');
        $this->session('+3 days', status: SessionStatus::Annulee);
        $this->reservation($this->user('a@example.test'), $upcoming);

        $this->client->request('GET', '/api/seances');

        self::assertResponseIsSuccessful();
        $items = $this->json();
        self::assertCount(1, $items);
        self::assertSame($upcoming->getId(), $items[0]['id']);
        self::assertSame(2, $items[0]['placesDisponibles']);
        self::assertSame('Yoga', $items[0]['activite']['nom']);
        // Dates ISO 8601 avec décalage : le navigateur les convertit dans son fuseau.
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $items[0]['dateDebut']);
    }

    public function testPastSessionDetailIsHidden(): void
    {
        $past = $this->session('-1 day');

        $this->client->request('GET', '/api/seances/' . $past->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testReservationRequiresAuthentication(): void
    {
        $session = $this->session('+2 days');

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $session->getId()]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testSportifReservesOnePlaceAndCannotReserveTwice(): void
    {
        $session = $this->session('+2 days', price: '15.00');
        $this->loginWithJwt($this->client, $this->user('a@example.test'));

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(201);
        $response = $this->json();
        self::assertSame('confirmee', $response['statut']);
        self::assertSame(1, $response['nombrePlaces']);
        // SQLite (tests) et MySQL ne formatent pas les décimaux de la même façon : comparaison numérique.
        self::assertEqualsWithDelta(15.0, (float) $response['montantTotal'], 0.001);
        self::assertQueuedEmailCount(1);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Vous avez déjà réservé cette séance.', $this->json()['message']);
    }

    public function testFullSessionIsRefused(): void
    {
        $session = $this->session('+2 days', capacity: 1);
        $this->reservation($this->user('a@example.test'), $session);
        $this->loginWithJwt($this->client, $this->user('b@example.test'));

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $session->getId()]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('Il ne reste plus de place.', $this->json()['message']);
    }

    public function testCancelledReservationIsReactivatedInsteadOfDuplicated(): void
    {
        $session = $this->session('+2 days');
        $athlete = $this->user('a@example.test');
        $old = $this->reservation($athlete, $session, ReservationStatus::Annulee);
        $this->loginWithJwt($this->client, $athlete);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $session->getId()]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($old->getId(), $this->json()['id']);
    }

    public function testUnavailableSessionsAndSeveralPlacesAreRefused(): void
    {
        $this->loginWithJwt($this->client, $this->user('a@example.test'));

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $this->session('-1 hour')->getId()]);
        self::assertResponseStatusCodeSame(409);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $this->session('+1 day', status: SessionStatus::Annulee)->getId()]);
        self::assertResponseStatusCodeSame(409);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => 9999]);
        self::assertResponseStatusCodeSame(404);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => $this->session('+1 day')->getId(), 'nombrePlaces' => 2]);
        // Corps refusé par le JSON Schema (nombrePlaces vaut toujours 1).
        self::assertResponseStatusCodeSame(422);
    }

    private function user(string $email, string $role = User::ROLE_SPORTIF): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Test')->setLastName('Utilisateur')
            ->setPassword('test-hash')->setRoles([$role])->setIsVerified(true);
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function session(string $start, int $capacity = 10, string $price = '10.00', SessionStatus $status = SessionStatus::Active): Session
    {
        $startAt = new \DateTimeImmutable($start);
        // Rechargées : une requête HTTP du client vide l'EntityManager.
        $activity = $this->em()->find(Activity::class, $this->activity->getId());
        $gym = $this->em()->find(Gym::class, $this->gym->getId());
        $session = (new Session())->setActivity($activity)->setGym($gym)->setTitle('Yoga test')
            ->setStartAt($startAt)->setEndAt($startAt->modify('+1 hour'))->setCapacity($capacity)
            ->setPrice($price)->setLevel(SessionLevel::Debutant)->setStatus($status);
        $this->em()->persist($session);
        $this->em()->flush();

        return $session;
    }

    private function reservation(User $user, Session $session, ReservationStatus $status = ReservationStatus::Confirmee): Reservation
    {
        $reservation = (new Reservation())->setUser($user)->setSession($session)->setStatus($status);
        $this->em()->persist($reservation);
        $this->em()->flush();

        return $reservation;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array<mixed> */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
