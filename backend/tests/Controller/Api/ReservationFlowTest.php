<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Parcours de réservation du sportif : JSON Schema, mes réservations, annulation (RG-05),
 * QR code et notifications internes.
 */
final class ReservationFlowTest extends ApiTestCase
{
    public function testBodyIsValidatedByJsonSchema(): void
    {
        $this->login($this->user('a@example.test'));

        $this->send('POST', '/api/reservations', ['seanceId' => 'douze', 'nombrePlaces' => 2, 'pirate' => true]);

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json()['errors'];
        self::assertArrayHasKey('seanceId', $errors);
        self::assertArrayHasKey('nombrePlaces', $errors);
        self::assertArrayHasKey('pirate', $errors);

        $this->send('POST', '/api/reservations', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Ce champ est obligatoire.', $this->json()['errors']['seanceId']);
    }

    public function testReservationHasQrTokenAndNotification(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $session = $this->session($this->activity(), $this->gym($manager), null);
        $this->login($this->user('a@example.test'));

        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->json()['qrToken']);

        $this->client->request('GET', '/api/me/notifications');
        self::assertSame(1, $this->json()['nonLues']);
        self::assertSame('reservation_confirmee', $this->json()['items'][0]['type']);

        $this->client->request('POST', '/api/me/notifications/lues');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/me/notifications');
        self::assertSame(0, $this->json()['nonLues']);
    }

    public function testUpcomingAndHistoryTabs(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $activity = $this->activity();
        $athlete = $this->user('a@example.test');
        $this->reservation($athlete, $this->session($activity, $gym, null, '+2 days', title: 'À venir'));
        $this->reservation($athlete, $this->session($activity, $gym, null, '+3 days', title: 'Annulée'), ReservationStatus::Annulee);
        $this->reservation($athlete, $this->session($activity, $gym, null, '-3 days', title: 'Passée'), ReservationStatus::Terminee);
        $this->reservation($this->user('b@example.test'), $this->session($activity, $gym, null, '+2 days', title: 'Pas à moi'));
        $this->login($athlete);

        $this->client->request('GET', '/api/reservations?periode=a_venir');
        self::assertSame(['À venir'], array_column(array_column($this->json()['items'], 'seance'), 'titre'));
        self::assertTrue($this->json()['items'][0]['annulable']);

        $this->client->request('GET', '/api/reservations?periode=historique');
        self::assertEqualsCanonicalizing(['Annulée', 'Passée'], array_column(array_column($this->json()['items'], 'seance'), 'titre'));
    }

    public function testCancellationRules(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $activity = $this->activity();
        $athlete = $this->user('a@example.test');
        $later = $this->reservation($athlete, $this->session($activity, $gym, null, '+1 day', capacity: 1));
        $tooLate = $this->reservation($athlete, $this->session($activity, $gym, null, '+90 minutes'));
        $notMine = $this->reservation($this->user('b@example.test'), $this->session($activity, $gym, null, '+2 days'));
        $this->login($athlete);

        $this->client->request('POST', '/api/reservations/' . $tooLate->getId() . '/annuler');
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('2 heures', $this->json()['message']);

        $this->client->request('POST', '/api/reservations/' . $notMine->getId() . '/annuler');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/reservations/' . $later->getId() . '/annuler');
        self::assertResponseIsSuccessful();
        self::assertSame('annulee', $this->json()['statut']);
        self::assertSame(1, $this->json()['seance']['placesDisponibles']);
        self::assertEmailCount(1);

        $this->client->request('POST', '/api/reservations/' . $later->getId() . '/annuler');
        self::assertResponseStatusCodeSame(409);
    }

    public function testAthleteCannotReadSomeoneElseReservation(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $reservation = $this->reservation($this->user('b@example.test'), $this->session($this->activity(), $this->gym($manager), null));
        $this->login($this->user('a@example.test'));

        $this->client->request('GET', '/api/reservations/' . $reservation->getId());
        self::assertResponseStatusCodeSame(403);

        // Le gestionnaire de la salle, lui, la voit.
        $this->login($manager);
        $this->client->request('GET', '/api/reservations/' . $reservation->getId());
        self::assertResponseIsSuccessful();
    }
}
