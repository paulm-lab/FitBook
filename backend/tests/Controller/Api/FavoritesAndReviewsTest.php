<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Favoris (EF-08), avis (EF-09, RG-06 à RG-08) et modération (EF-14).
 */
final class FavoritesAndReviewsTest extends ApiTestCase
{
    public function testFavoritesCanBeAddedListedAndRemovedByTheirOwnerOnly(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager, 'Ma salle préférée');
        $athlete = $this->user('a@example.test');
        $this->login($athlete);

        $this->send('POST', '/api/favoris', ['type' => 'salle', 'cibleId' => $gym->getId()]);
        self::assertResponseStatusCodeSame(201);
        $favoriteId = $this->json()['id'];
        self::assertSame('Ma salle préférée', $this->json()['cible']['nom']);

        $this->send('POST', '/api/favoris', ['type' => 'salle', 'cibleId' => $gym->getId()]);
        self::assertResponseStatusCodeSame(409);

        $this->send('POST', '/api/favoris', ['type' => 'planete', 'cibleId' => 1]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/favoris');
        self::assertCount(1, $this->json());
        self::assertSame('salle', $this->json()[0]['type']);

        $this->login($this->user('b@example.test'));
        $this->client->request('DELETE', '/api/favoris/' . $favoriteId);
        self::assertResponseStatusCodeSame(404);

        $this->login($athlete);
        $this->client->request('DELETE', '/api/favoris/' . $favoriteId);
        self::assertResponseStatusCodeSame(204);
    }

    public function testReviewRequiresAFinishedReservationAndIsUnique(): void
    {
        $coachUser = $this->user('coach@example.test', User::ROLE_COACH);
        $coach = $this->reload($coachUser)->getCoachProfile();
        $athlete = $this->user('a@example.test');
        $this->login($athlete);

        $this->send('POST', '/api/avis', ['type' => 'coach', 'cibleId' => $coach->getId(), 'note' => 5]);
        self::assertResponseStatusCodeSame(403);

        // Séance finie, réservation encore « confirmée » : l'API la passe à « terminée ».
        $past = $this->session($this->activity(), null, $coach, '-3 hours');
        $this->reservation($athlete, $past, ReservationStatus::Confirmee);

        $this->send('POST', '/api/avis', ['type' => 'coach', 'cibleId' => $coach->getId(), 'note' => 9]);
        self::assertResponseStatusCodeSame(422);

        $this->send('POST', '/api/avis', ['type' => 'coach', 'cibleId' => $coach->getId(), 'note' => 4, 'commentaire' => '<script>alert(1)</script> Super']);
        self::assertResponseStatusCodeSame(201);
        $reviewId = $this->json()['id'];
        // Stocké et renvoyé tel quel : c'est le front qui l'affiche en texte (jamais en HTML).
        self::assertSame('<script>alert(1)</script> Super', $this->json()['commentaire']);

        $this->send('POST', '/api/avis', ['type' => 'coach', 'cibleId' => $coach->getId(), 'note' => 3]);
        self::assertResponseStatusCodeSame(409);

        $this->send('PUT', '/api/avis/' . $reviewId, ['note' => 2]);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['note']);

        $this->client->request('GET', '/api/coaches/' . $coach->getId());
        self::assertEquals(2.0, $this->json()['note']['note']);
    }

    public function testHiddenReviewDisappearsFromRatingAndOnlyAdminModerates(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $good = $this->review($this->user('a@example.test'), $gym, 5);
        $abusive = $this->review($this->user('b@example.test'), $gym, 1);

        $this->client->request('GET', '/api/salles/' . $gym->getId());
        self::assertEquals(3.0, $this->json()['note']['note']);

        $this->login($manager);
        $this->send('PATCH', '/api/admin/avis/' . $abusive->getId(), ['action' => 'masquer']);
        self::assertResponseStatusCodeSame(403);

        $this->login($this->user('admin@example.test', User::ROLE_ADMIN));
        $this->client->request('GET', '/api/admin/avis?filtre=non_moderes');
        self::assertSame(2, $this->json()['total']);

        $this->send('PATCH', '/api/admin/avis/' . $abusive->getId(), ['action' => 'masquer']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['estVisible']);
        $this->send('PATCH', '/api/admin/avis/' . $good->getId(), ['action' => 'valider']);
        self::assertTrue($this->json()['estModere']);

        $this->client->request('GET', '/api/admin/avis?filtre=non_moderes');
        self::assertSame(0, $this->json()['total']);

        $this->logout();
        $this->client->request('GET', '/api/salles/' . $gym->getId());
        self::assertEquals(['note' => 5.0, 'nbAvis' => 1], $this->json()['note']);
        self::assertCount(1, $this->json()['avis']);
    }
}
