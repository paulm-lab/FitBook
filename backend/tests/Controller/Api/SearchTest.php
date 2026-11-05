<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Enum\SessionLevel;
use App\Enum\SessionStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Recherche avancée paginée /api/recherche (EF-01, US-V01 à US-V03).
 */
final class SearchTest extends ApiTestCase
{
    public function testSessionsAreFilteredAndPaginated(): void
    {
        $manager = $this->user('gestion@example.test', User::ROLE_GESTIONNAIRE);
        $yoga = $this->activity('Yoga');
        $boxe = $this->activity('Boxe');
        $ixelles = $this->gym($manager, 'Salle Flagey', 'Ixelles');
        $jette = $this->gym($manager, 'Salle Jette', 'Jette');

        $this->session($yoga, $ixelles, null, '+1 day', price: '12.00', title: 'Yoga doux');
        $this->session($yoga, $jette, null, '+2 days', price: '8.00', level: SessionLevel::Avance, title: 'Yoga dynamique');
        $this->session($boxe, $ixelles, null, '+3 days', price: '20.00', title: 'Boxe');
        $this->session($yoga, $ixelles, null, '-1 day', title: 'Yoga passé');
        $this->session($yoga, $ixelles, null, '+1 day', status: SessionStatus::Annulee, title: 'Yoga annulé');

        $this->client->request('GET', '/api/recherche?type=seances&activite=yoga');
        self::assertResponseIsSuccessful();
        $page = $this->json();
        self::assertSame(2, $page['total']);
        self::assertSame(['Yoga doux', 'Yoga dynamique'], array_column($page['items'], 'titre'));
        self::assertSame(['items', 'page', 'parPage', 'total', 'pages'], array_keys($page));

        $this->client->request('GET', '/api/recherche?activite=yoga&tri=prix');
        self::assertSame(['Yoga dynamique', 'Yoga doux'], array_column($this->json()['items'], 'titre'));

        $this->client->request('GET', '/api/recherche?ville=Ixelles&prixMax=15');
        self::assertSame(['Yoga doux'], array_column($this->json()['items'], 'titre'));

        $this->client->request('GET', '/api/recherche?niveau=avance');
        self::assertSame(['Yoga dynamique'], array_column($this->json()['items'], 'titre'));

        $this->client->request('GET', '/api/recherche?parPage=2&page=2');
        $page = $this->json();
        self::assertSame(3, $page['total']);
        self::assertSame(2, $page['pages']);
        self::assertCount(1, $page['items']);
    }

    public function testMinimumRatingUsesVisibleReviewsOnly(): void
    {
        $manager = $this->user('gestion@example.test', User::ROLE_GESTIONNAIRE);
        $author = $this->user('a@example.test');
        $good = $this->gym($manager, 'Bonne salle');
        $hidden = $this->gym($manager, 'Salle masquée');
        $this->review($author, $good, 5);
        $this->review($author, $hidden, 5, visible: false);

        $this->client->request('GET', '/api/recherche?type=salles&noteMin=4');

        self::assertResponseIsSuccessful();
        $items = $this->json()['items'];
        self::assertSame(['Bonne salle'], array_column($items, 'nom'));
        self::assertEquals(5.0, $items[0]['noteMoyenne']);
    }

    public function testGymsAroundAPointAreSortedByDistance(): void
    {
        $manager = $this->user('gestion@example.test', User::ROLE_GESTIONNAIRE);
        $this->gym($manager, 'Loin', 'Liège', '50.632500', '5.579700');
        $this->gym($manager, 'Proche', 'Bruxelles', '50.846000', '4.352000');
        $this->gym($manager, 'Moyen', 'Uccle', '50.793000', '4.365000');

        $this->client->request('GET', '/api/recherche?type=salles&lat=50.8466&lng=4.3528&rayon=20&tri=distance');

        self::assertResponseIsSuccessful();
        $items = $this->json()['items'];
        self::assertSame(['Proche', 'Moyen'], array_column($items, 'nom'));
        self::assertLessThan(1.0, $items[0]['distanceKm']);
    }

    public function testCoachesAreSearchedBySpecialityAndRate(): void
    {
        $this->user('coach1@example.test', User::ROLE_COACH, 'Léa', 'Martin');
        $expensive = $this->user('coach2@example.test', User::ROLE_COACH, 'Tom', 'Dubois');
        $this->reload($expensive)->getCoachProfile()->setHourlyRate('90.00')->setSpecialities(['Boxe']);
        $this->em()->flush();

        $this->client->request('GET', '/api/recherche?type=coachs&q=yoga&prixMax=50');

        self::assertResponseIsSuccessful();
        self::assertSame(['Martin'], array_column($this->json()['items'], 'nom'));
    }

    public function testInvalidCriteriaAreRefusedWithFieldErrors(): void
    {
        $this->client->request('GET', '/api/recherche?type=planetes&du=2026-13-45&noteMin=9&tri=distance');

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json()['errors'];
        self::assertArrayHasKey('type', $errors);
        self::assertArrayHasKey('du', $errors);
        self::assertArrayHasKey('noteMin', $errors);
        self::assertArrayHasKey('tri', $errors);
    }
}
