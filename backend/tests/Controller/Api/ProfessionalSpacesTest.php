<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;

/**
 * Espaces coach, gestionnaire et administrateur : salles, profil professionnel,
 * disponibilités, statistiques, comptes et activités.
 */
final class ProfessionalSpacesTest extends ApiTestCase
{
    /** @return array<string, mixed> */
    private function gymPayload(string $name = 'FitBook Louise'): array
    {
        return [
            'nom' => $name,
            'adresse' => 'Avenue Louise 100',
            'ville' => 'Bruxelles',
            'codePostal' => '1050',
            'latitude' => 50.83,
            'longitude' => 4.36,
            'horaires' => ['lundi' => '07:00-22:00'],
        ];
    }

    public function testManagerCreatesAndEditsOnlyHisGyms(): void
    {
        $manager = $this->user('g1@example.test', User::ROLE_GESTIONNAIRE);
        $other = $this->gym($this->user('g2@example.test', User::ROLE_GESTIONNAIRE), 'Pas à moi');
        $this->login($manager);

        $this->send('POST', '/api/salles', ['horaires' => ['lundi' => '22:00-07:00']] + $this->gymPayload(''));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('nom', $this->json()['errors']);
        self::assertArrayHasKey('horaires', $this->json()['errors']);

        $this->send('POST', '/api/salles', $this->gymPayload());
        self::assertResponseStatusCodeSame(201);
        $gymId = $this->json()['id'];

        $this->send('PUT', '/api/salles/' . $other->getId(), $this->gymPayload('Piratée'));
        self::assertResponseStatusCodeSame(403);

        $this->send('PUT', '/api/salles/' . $gymId, $this->gymPayload('FitBook Louise 2'));
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/gestionnaire/salles');
        self::assertSame(['FitBook Louise 2'], array_column($this->json(), 'nom'));
        self::assertArrayHasKey('tauxRemplissage', $this->json()[0]['stats']);

        $this->client->request('GET', '/api/salles/' . $gymId . '/stats');
        self::assertResponseIsSuccessful();
        self::assertCount(12, $this->json()['evolution']);

        $this->client->request('GET', '/api/salles/' . $other->getId() . '/stats');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSportifCannotCreateGymOrOpenProfessionalSpaces(): void
    {
        $this->login($this->user('a@example.test'));

        $this->send('POST', '/api/salles', $this->gymPayload());
        self::assertResponseStatusCodeSame(403);

        foreach (['/api/coach/profil', '/api/gestionnaire/salles', '/api/admin/stats'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403, $uri);
        }
    }

    public function testCoachUpdatesProfileAndAvailabilities(): void
    {
        $coach = $this->user('coach@example.test', User::ROLE_COACH);
        $this->login($coach);

        $this->send('PUT', '/api/coach/profil', ['biographie' => 'Coach de yoga.', 'specialites' => ['Yoga', 'Pilates'], 'experienceAnnees' => 6, 'tarifHoraire' => 45, 'ville' => 'Ixelles']);
        self::assertResponseIsSuccessful();
        self::assertSame(['Yoga', 'Pilates'], $this->json()['specialites']);

        $this->send('POST', '/api/disponibilites', ['recurrent' => true, 'jourSemaine' => 1, 'heureDebut' => '12:00', 'heureFin' => '09:00']);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('heureFin', $this->json()['errors']);

        $this->send('POST', '/api/disponibilites', ['recurrent' => true, 'jourSemaine' => 1, 'heureDebut' => '09:00', 'heureFin' => '12:00']);
        self::assertResponseStatusCodeSame(201);
        $slotId = $this->json()['id'];

        $this->client->request('GET', '/api/coaches/' . $this->reload($coach)->getCoachProfile()->getId());
        self::assertSame('Coach de yoga.', $this->json()['biographie']);
        self::assertSame('09:00', $this->json()['disponibilites'][0]['heureDebut']);

        $this->login($this->user('coach2@example.test', User::ROLE_COACH));
        $this->client->request('DELETE', '/api/disponibilites/' . $slotId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminManagesAccountsButNotHisOwnAdminRole(): void
    {
        $admin = $this->user('admin@example.test', User::ROLE_ADMIN);
        $member = $this->user('member@example.test', firstName: 'Léa', lastName: 'Martin');
        $this->login($admin);

        $this->client->request('GET', '/api/admin/utilisateurs?q=mart');
        self::assertSame(['member@example.test'], array_column($this->json()['items'], 'email'));

        $this->send('PATCH', '/api/admin/utilisateurs/' . $member->getId(), ['role' => 'coach', 'estBloque' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame('coach', $this->json()['role']);
        self::assertTrue($this->json()['estBloque']);
        self::assertNotNull($this->json()['coachId']);

        $this->send('PATCH', '/api/admin/utilisateurs/' . $admin->getId(), ['role' => 'sportif']);
        self::assertResponseStatusCodeSame(409);

        $this->send('PATCH', '/api/admin/utilisateurs/' . $admin->getId(), ['estBloque' => true]);
        self::assertResponseStatusCodeSame(409);

        $this->client->request('GET', '/api/admin/stats');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['utilisateursParRole']['admin']);
        self::assertSame(1, $this->json()['utilisateursParRole']['coach']);
    }

    public function testAdminManagesActivities(): void
    {
        $this->login($this->user('admin@example.test', User::ROLE_ADMIN));
        $this->activity('Yoga');

        $this->send('POST', '/api/activites', ['nom' => 'yoga', 'categorie' => 'Bien-être']);
        self::assertResponseStatusCodeSame(409);

        $this->send('POST', '/api/activites', ['nom' => 'Aquagym', 'categorie' => 'Bien-être']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];

        $this->client->request('DELETE', '/api/activites/' . $id);
        self::assertResponseStatusCodeSame(204);

        $this->login($this->user('coach@example.test', User::ROLE_COACH));
        $this->send('POST', '/api/activites', ['nom' => 'Padel', 'categorie' => 'Raquette']);
        self::assertResponseStatusCodeSame(403);
    }
}
