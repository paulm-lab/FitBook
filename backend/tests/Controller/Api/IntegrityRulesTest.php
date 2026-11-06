<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\CoachProfile;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Repository\ReservationRepository;
use App\Tests\Support\ApiTestCase;

/**
 * Règles d'intégrité : historique protégé, rattachement aux salles, visibilité des séances,
 * secret du QR code, changements de rôle, rappels et réservations réservées aux sportifs.
 */
final class IntegrityRulesTest extends ApiTestCase
{
    // --- Suppression et annulation d'une séance ------------------------------

    public function testStartedSessionCannotBeDeletedNorCancelled(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $session = $this->session($this->activity(), null, $this->reload($coach)->getCoachProfile(), '-3 hours');
        $this->reservation($this->user('a@example.test'), $session, ReservationStatus::Terminee);
        $this->login($coach);

        $this->client->request('DELETE', '/api/seances/' . $session->getId());
        self::assertResponseStatusCodeSame(409);

        $this->client->request('POST', '/api/seances/' . $session->getId() . '/annuler');
        self::assertResponseStatusCodeSame(409);

        $this->em()->clear();
        self::assertSame(SessionStatus::Active, $this->em()->find(Session::class, $session->getId())->getStatus());
        self::assertCount(1, $this->em()->getRepository(Reservation::class)->findBy(['session' => $session->getId()]));
    }

    public function testSessionWithReviewIsNotDeletedButOnlyCancelledReservationsDoNotBlock(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $profile = $this->reload($coach)->getCoachProfile();
        $activity = $this->activity();
        $reviewed = $this->session($activity, null, $profile);
        $this->review($this->user('a@example.test'), $reviewed, 5);
        $cancelledOnly = $this->session($activity, null, $profile);
        $this->reservation($this->user('b@example.test'), $cancelledOnly, ReservationStatus::Annulee);
        $this->login($coach);

        $this->client->request('DELETE', '/api/seances/' . $reviewed->getId());
        self::assertResponseStatusCodeSame(409);

        $this->client->request('DELETE', '/api/seances/' . $cancelledOnly->getId());
        self::assertResponseStatusCodeSame(204);
    }

    public function testFinishingIgnoresCancelledSessions(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $activity = $this->activity();
        $held = $this->reservation($this->user('a@example.test'), $this->session($activity, $gym, null, '-3 hours'));
        $cancelled = $this->reservation($this->user('b@example.test'), $this->session($activity, $gym, null, '-3 hours', status: SessionStatus::Annulee));

        $finished = static::getContainer()->get(ReservationRepository::class)->markFinished(new \DateTimeImmutable());

        self::assertSame(1, $finished);
        $this->em()->clear();
        self::assertSame(ReservationStatus::Terminee, $this->em()->find(Reservation::class, $held->getId())->getStatus());
        self::assertSame(ReservationStatus::Confirmee, $this->em()->find(Reservation::class, $cancelled->getId())->getStatus());
    }

    // --- Rattachement d'une séance à une salle -------------------------------

    public function testCoachCannotAttachNorDetachAGym(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $gym = $this->gym($this->user('g@example.test', User::ROLE_GESTIONNAIRE));
        $activity = $this->activity();
        $inGym = $this->session($activity, $gym, $this->reload($coach)->getCoachProfile());
        $this->login($coach);

        $this->send('POST', '/api/seances', $this->payload($activity->getId(), $gym->getId()));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('salleId', $this->json()['errors']);

        // La séance programmée par le gestionnaire reste dans sa salle.
        $this->send('PUT', '/api/seances/' . $inGym->getId(), $this->payload($activity->getId()));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('salleId', $this->json()['errors']);

        $this->send('PUT', '/api/seances/' . $inGym->getId(), $this->payload($activity->getId(), $gym->getId()));
        self::assertResponseIsSuccessful();
        self::assertSame($gym->getId(), $this->json()['salle']['id']);
    }

    // --- Fiche d'une séance ---------------------------------------------------

    public function testParticipantOpensHisPastSessionAndSeesWhetherHeBookedIt(): void
    {
        $gym = $this->gym($this->user('g@example.test', User::ROLE_GESTIONNAIRE));
        $activity = $this->activity();
        $past = $this->session($activity, $gym, null, '-3 days');
        $upcoming = $this->session($activity, $gym, null, '+3 days');
        $athlete = $this->user('a@example.test');
        $this->reservation($athlete, $past, ReservationStatus::Terminee);
        $this->reservation($athlete, $upcoming);

        $this->client->request('GET', '/api/seances/' . $upcoming->getId());
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['dejaReservee'], 'Visiteur anonyme.');

        $this->login($this->user('b@example.test'));
        $this->client->request('GET', '/api/seances/' . $past->getId());
        self::assertResponseStatusCodeSame(404);

        $this->login($athlete);
        $this->client->request('GET', '/api/seances/' . $past->getId());
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['dejaReservee']);

        $this->client->request('GET', '/api/seances/' . $upcoming->getId());
        self::assertTrue($this->json()['dejaReservee']);
    }

    public function testDateFilterUsesTheBrusselsDay(): void
    {
        $gym = $this->gym($this->user('g@example.test', User::ROLE_GESTIONNAIRE));
        $activity = $this->activity();
        $day = (new \DateTimeImmutable('+5 days', new \DateTimeZone('Europe/Brussels')))->setTime(0, 0);
        // Dates stockées en UTC : 00:30 à Bruxelles est encore la veille en UTC.
        $utc = static fn (\DateTimeImmutable $local): string => $local->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
        $this->session($activity, $gym, null, $utc($day->setTime(0, 30)), title: 'Minuit et demie');
        $this->session($activity, $gym, null, $utc($day->setTime(23, 30)), title: 'Fin de soirée');
        $this->session($activity, $gym, null, $utc($day->modify('+1 day')->setTime(0, 30)), title: 'Lendemain');

        $this->client->request('GET', '/api/seances?date=' . $day->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        self::assertSame(['Minuit et demie', 'Fin de soirée'], array_column($this->json(), 'titre'));
    }

    public function testSessionsOfABlockedCoachAreHiddenAndNotBookable(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $session = $this->session($this->activity(), null, $this->reload($coach)->getCoachProfile());
        $this->reload($coach)->setIsBlocked(true);
        $this->em()->flush();

        $this->client->request('GET', '/api/seances');
        self::assertSame([], $this->json());
        $this->client->request('GET', '/api/recherche?type=seances');
        self::assertSame(0, $this->json()['total']);
        $this->client->request('GET', '/api/seances/' . $session->getId());
        self::assertResponseStatusCodeSame(404);

        $this->login($this->user('a@example.test'));
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(409);
    }

    // --- Réservations ---------------------------------------------------------

    public function testOnlyAthletesBook(): void
    {
        $gym = $this->gym($this->user('g@example.test', User::ROLE_GESTIONNAIRE));
        $session = $this->session($this->activity(), $gym, null);

        foreach ([User::ROLE_ADMIN, User::ROLE_COACH, User::ROLE_GESTIONNAIRE] as $i => $role) {
            $this->login($this->user("pro{$i}@example.test", $role));
            $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
            self::assertResponseStatusCodeSame(403, $role);
        }

        $this->login($this->user('a@example.test'));
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testQrTokenIsOnlyShownToTheAthlete(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $session = $this->session($this->activity(), $this->gym($manager), null);
        $athlete = $this->user('a@example.test');
        $this->login($athlete);
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        $id = $this->json()['id'];
        $token = $this->json()['qrToken'];
        self::assertNotNull($token);

        $this->client->request('GET', '/api/reservations/' . $id);
        self::assertSame($token, $this->json()['qrToken']);

        $this->login($manager);
        $this->client->request('GET', '/api/seances/' . $session->getId() . '/inscrits');
        self::assertNull($this->json()['inscrits'][0]['qrToken']);
        $this->client->request('GET', '/api/reservations/' . $id);
        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['qrToken']);
    }

    public function testReactivatedReservationAndRescheduledSessionGetANewReminder(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $activity = $this->activity();
        $session = $this->session($activity, null, $this->reload($coach)->getCoachProfile(), '+20 hours');
        $athlete = $this->user('a@example.test');
        $reservation = $this->reservation($athlete, $session, ReservationStatus::Annulee);
        $this->reload($reservation)->setReminderSentAt(new \DateTimeImmutable());
        $other = $this->reservation($this->user('b@example.test'), $session);
        $this->reload($other)->setReminderSentAt(new \DateTimeImmutable());
        $this->em()->flush();

        $this->login($athlete);
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(201);
        $this->em()->clear();
        self::assertNull($this->em()->find(Reservation::class, $reservation->getId())->getReminderSentAt());

        $this->login($coach);
        $this->send('PUT', '/api/seances/' . $session->getId(), $this->payload($activity->getId(), null, '+30 hours'));
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertNull($this->em()->find(Reservation::class, $other->getId())->getReminderSentAt());
    }

    // --- Administration et espace coach ---------------------------------------

    public function testRoleChangeIsRefusedWhileGymsOrUpcomingSessionsRemain(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $this->gym($manager);
        $busyCoach = $this->user('coach1@example.test', User::ROLE_COACH);
        $this->session($this->activity(), null, $this->reload($busyCoach)->getCoachProfile());
        $freeCoach = $this->user('coach2@example.test', User::ROLE_COACH, lastName: 'Libre');
        $this->login($this->user('admin@example.test', User::ROLE_ADMIN));

        $this->send('PATCH', '/api/admin/utilisateurs/' . $manager->getId(), ['role' => 'sportif']);
        self::assertResponseStatusCodeSame(409);
        $this->send('PATCH', '/api/admin/utilisateurs/' . $busyCoach->getId(), ['role' => 'sportif']);
        self::assertResponseStatusCodeSame(409);

        $this->send('PATCH', '/api/admin/utilisateurs/' . $freeCoach->getId(), ['role' => 'sportif']);
        self::assertResponseIsSuccessful();
        // Son ancien profil de coach n'est plus publié.
        $this->client->request('GET', '/api/coaches');
        self::assertNotContains('Libre', array_column($this->json(), 'nom'));
    }

    public function testAdministratorDoesNotBecomeAPublicCoach(): void
    {
        $admin = $this->user('admin@example.test', User::ROLE_ADMIN);
        $this->login($admin);

        $this->client->request('GET', '/api/coach/profil');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em()->getRepository(CoachProfile::class)->count([]));
    }

    // --- Recherche et pagination ----------------------------------------------

    public function testRadiusDoesNotExcludeCoaches(): void
    {
        $this->user('coach@example.test', User::ROLE_COACH, 'Léa', 'Martin');

        $this->client->request('GET', '/api/recherche?type=coachs&lat=50.85&lng=4.35&rayon=10');

        self::assertResponseIsSuccessful();
        self::assertSame(['Martin'], array_column($this->json()['items'], 'nom'));
    }

    public function testHugePageNumberIsClamped(): void
    {
        $this->client->request('GET', '/api/recherche?page=' . \PHP_INT_MAX);

        self::assertResponseIsSuccessful();
        self::assertSame(10000, $this->json()['page']);
    }

    /** @return array<string, mixed> */
    private function payload(int $activityId, ?int $gymId = null, string $start = '+3 days'): array
    {
        $startAt = new \DateTimeImmutable($start);

        return [
            'titre' => 'Yoga du soir',
            'activiteId' => $activityId,
            'salleId' => $gymId,
            'debut' => $startAt->format(\DATE_ATOM),
            'fin' => $startAt->modify('+75 minutes')->format(\DATE_ATOM),
            'capacite' => 12,
            'prix' => 14.5,
            'niveau' => 'intermediaire',
        ];
    }
}
