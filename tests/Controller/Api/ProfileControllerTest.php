<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ProfileControllerTest extends WebTestCase
{
    public function testAnonymousVisitorCannotReadOwnProfile(): void
    {
        $client = $this->clientWithSchema();
        $client->request('GET', '/api/me/profile');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedUserReadsOnlySafeProfileFields(): void
    {
        $client = $this->clientWithSchema();
        $user = $this->createUser('lea@example.test', 'Martin', 'Lea', $client);

        $client->request('GET', '/api/me/profile');

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['id' => $user->getId(), 'nom' => 'Martin', 'prenom' => 'Lea', 'email' => 'lea@example.test', 'telephone' => '0612345678'], $response);
        self::assertArrayNotHasKey('motDePasse', json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testAnonymousVisitorCannotUpdateProfile(): void
    {
        $client = $this->clientWithSchema();
        $client->jsonRequest('PUT', '/api/me/profile', ['nom' => 'Autre', 'prenom' => 'Compte', 'telephone' => '0612345678']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProfileUpdateChangesOnlyTheAuthenticatedUserAndIgnoresSubmittedIds(): void
    {
        $client = $this->clientWithSchema();
        $user = $this->createUser('lea@example.test', 'Martin', 'Lea', $client);
        $other = $this->createUser('paul@example.test', 'Durand', 'Paul');

        $client->jsonRequest('PUT', '/api/me/profile', ['id' => $other->getId(), 'nom' => 'Nouveau', 'prenom' => 'Profil', 'telephone' => '0712345678']);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['id' => $user->getId(), 'nom' => 'Nouveau', 'prenom' => 'Profil', 'email' => 'lea@example.test', 'telephone' => '0712345678'], $response);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame('Durand', $em->find(Utilisateur::class, $other->getId())->getNom());
    }

    public function testInvalidProfileUpdateIsRejectedBeforePersistence(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('lea@example.test', 'Martin', 'Lea', $client);

        $client->jsonRequest('PUT', '/api/me/profile', ['nom' => ['invalid'], 'prenom' => 'Lea', 'telephone' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        $errors = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['errors'];
        self::assertArrayHasKey('nom', $errors);
        self::assertArrayHasKey('telephone', $errors);
    }

    public function testUserDirectoryRequiresAdministratorRole(): void
    {
        $client = $this->clientWithSchema();
        $client->request('GET', '/api/utilisateurs');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRegularUserCannotListAllUsers(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('member@example.test', 'Martin', 'Lea', $client);
        $client->request('GET', '/api/utilisateurs');

        self::assertResponseStatusCodeSame(403);
    }
    private function clientWithSchema(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!$em->getConnection()->createSchemaManager()->tablesExist(['utilisateur'])) {
            (new SchemaTool($em))->createSchema([$em->getClassMetadata(Utilisateur::class)]);
        }

        return $client;
    }

    private function createUser(string $email, string $nom, string $prenom, ?KernelBrowser $client = null): Utilisateur
    {
        $user = (new Utilisateur())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setMotDePasse('test-hash')
            ->setTelephone('0612345678')
            ->setRole('ROLE_USER')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatut('actif');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
        $client?->loginUser($user);

        return $user;
    }
}
