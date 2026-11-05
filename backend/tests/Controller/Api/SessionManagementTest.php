<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Gestion des séances par les coachs et les gestionnaires (US-C02, US-C05, US-G02, RG-09 à RG-12).
 */
final class SessionManagementTest extends ApiTestCase
{
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

    public function testCoachCreatesASessionAttachedToHisProfile(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $activity = $this->activity();
        $this->login($coach);

        $this->send('POST', '/api/seances', $this->payload($activity->getId()));

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame($this->reload($coach)->getCoachProfile()->getId(), $body['coach']['id']);
        self::assertNull($body['salle']);
        self::assertSame(12, $body['placesDisponibles']);
        self::assertSame('intermediaire', $body['niveau']);
    }

    public function testInvalidSessionIsRefusedFieldByField(): void
    {
        $this->login($this->user('coach@example.test', User::ROLE_COACH));
        $start = new \DateTimeImmutable('+1 day');

        $this->send('POST', '/api/seances', [
            'titre' => '',
            'activiteId' => 999,
            'debut' => $start->format(\DATE_ATOM),
            'fin' => $start->modify('-1 hour')->format(\DATE_ATOM),
            'capacite' => 0,
            'prix' => -3,
        ]);

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json()['errors'];
        foreach (['titre', 'fin', 'capacite', 'prix'] as $field) {
            self::assertArrayHasKey($field, $errors);
        }
    }

    public function testSportifCannotCreateAndAnonymousIsUnauthenticated(): void
    {
        $activity = $this->activity();

        $this->send('POST', '/api/seances', $this->payload($activity->getId()));
        self::assertResponseStatusCodeSame(401);

        $this->login($this->user('a@example.test'));
        $this->send('POST', '/api/seances', $this->payload($activity->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testCoachCannotEditAnotherCoachSession(): void
    {
        $owner = $this->user('coach1@example.test', User::ROLE_COACH);
        $other = $this->user('coach2@example.test', User::ROLE_COACH);
        $activity = $this->activity();
        $session = $this->session($activity, null, $this->reload($owner)->getCoachProfile());
        $this->login($other);

        $this->send('PUT', '/api/seances/' . $session->getId(), $this->payload($activity->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/seances/' . $session->getId() . '/inscrits');
        self::assertResponseStatusCodeSame(403);
    }

    public function testManagerMustUseOneOfHisGyms(): void
    {
        $manager = $this->user('g1@example.test', User::ROLE_GESTIONNAIRE);
        $otherManager = $this->user('g2@example.test', User::ROLE_GESTIONNAIRE);
        $mine = $this->gym($manager, 'Ma salle');
        $notMine = $this->gym($otherManager, 'Autre salle');
        $activity = $this->activity();
        $this->login($manager);

        $this->send('POST', '/api/seances', $this->payload($activity->getId(), $notMine->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->send('POST', '/api/seances', $this->payload($activity->getId()));
        self::assertResponseStatusCodeSame(422);

        $this->send('POST', '/api/seances', $this->payload($activity->getId(), $mine->getId()));
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Ma salle', $this->json()['salle']['nom']);
    }

    public function testCapacityCannotDropBelowConfirmedReservations(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $activity = $this->activity();
        $session = $this->session($activity, null, $this->reload($coach)->getCoachProfile());
        $this->reservation($this->user('a@example.test'), $session);
        $this->reservation($this->user('b@example.test'), $session);
        $this->login($coach);

        $this->send('PUT', '/api/seances/' . $session->getId(), ['capacite' => 1] + $this->payload($activity->getId()));

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('capacite', $this->json()['errors']);
    }

    public function testCancellingASessionCancelsReservationsAndWarnsAthletes(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $session = $this->session($this->activity(), null, $this->reload($coach)->getCoachProfile());
        $first = $this->reservation($this->user('a@example.test'), $session);
        $this->reservation($this->user('b@example.test'), $session);
        $this->reservation($this->user('c@example.test'), $session, ReservationStatus::Annulee);
        $this->login($coach);

        // Suppression refusée tant que des sportifs sont inscrits : il faut annuler.
        $this->client->request('DELETE', '/api/seances/' . $session->getId());
        self::assertResponseStatusCodeSame(409);

        $this->client->request('POST', '/api/seances/' . $session->getId() . '/annuler');
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['sportifsPrevenus']);
        self::assertQueuedEmailCount(2);

        $this->em()->clear();
        self::assertSame(SessionStatus::Annulee, $this->em()->find(Session::class, $session->getId())->getStatus());
        self::assertSame(ReservationStatus::Annulee, $this->em()->find(Reservation::class, $first->getId())->getStatus());

        // Une séance annulée n'est plus réservable (RG-12).
        $this->login($this->user('d@example.test'));
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testOrganizerSeesAttendees(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $session = $this->session($this->activity(), $gym, null);
        $this->reservation($this->user('a@example.test', firstName: 'Alice'), $session);
        $this->login($manager);

        $this->client->request('GET', '/api/seances/' . $session->getId() . '/inscrits');

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame(1, $body['nbConfirmes']);
        self::assertSame('Alice', $body['inscrits'][0]['sportif']['prenom']);
        self::assertSame('a@example.test', $body['inscrits'][0]['sportif']['email']);
    }

    public function testSessionWithoutReservationIsDeleted(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $session = $this->session($this->activity(), null, $this->reload($coach)->getCoachProfile());
        $sessionId = $session->getId();
        $this->login($coach);

        $this->client->request('DELETE', '/api/seances/' . $sessionId);

        self::assertResponseStatusCodeSame(204);
        $this->em()->clear();
        self::assertNull($this->em()->find(Session::class, $sessionId));
    }

    public function testCoachCalendarListsOnlyHisSessionsInTheWindow(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $other = $this->user('coach2@example.test', User::ROLE_COACH);
        $activity = $this->activity();
        $this->session($activity, null, $this->reload($coach)->getCoachProfile(), '+1 day', title: 'Dans la vue');
        $this->session($activity, null, $this->reload($coach)->getCoachProfile(), '+20 days', title: 'Hors vue');
        $this->session($activity, null, $this->reload($other)->getCoachProfile(), '+1 day', title: 'Autre coach');
        $this->login($coach);

        $from = rawurlencode((new \DateTimeImmutable('today'))->format(\DATE_ATOM));
        $to = rawurlencode((new \DateTimeImmutable('+7 days'))->format(\DATE_ATOM));
        $this->client->request('GET', "/api/coach/seances?debut={$from}&fin={$to}");

        self::assertResponseIsSuccessful();
        self::assertSame(['Dans la vue'], array_column($this->json(), 'titre'));
    }
}
