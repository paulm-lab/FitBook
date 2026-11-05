<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\Support\JwtLoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Profil de l'utilisateur connecté (/api/me/profile) et liste des comptes réservée à l'admin.
 */
final class ProfileControllerTest extends WebTestCase
{
    use JwtLoginTrait;

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
        self::assertSame([
            'id' => $user->getId(),
            'nom' => 'Martin',
            'prenom' => 'Lea',
            'email' => 'lea@example.test',
            'telephone' => '0612345678',
            'avatar' => null,
            'roles' => [User::ROLE_SPORTIF],
            'role' => 'sportif',
            'coachId' => null,
        ], $response);
        self::assertArrayNotHasKey('password', $response);
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
        self::assertSame($user->getId(), $response['id']);
        self::assertSame('Nouveau', $response['nom']);
        self::assertSame('0712345678', $response['telephone']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame('Durand', $em->find(User::class, $other->getId())->getLastName());
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

    public function testUserDirectoryRequiresAuthentication(): void
    {
        $client = $this->clientWithSchema();
        $client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSportifCannotListAllUsers(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('member@example.test', 'Martin', 'Lea', $client);
        $client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorListsUsers(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('admin@example.test', 'Admin', 'Root', $client, User::ROLE_ADMIN);
        $this->createUser('member@example.test', 'Martin', 'Lea');

        $client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseIsSuccessful();
        self::assertCount(2, json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['items']);
    }

    private function clientWithSchema(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Base SQLite en mémoire : le schéma complet est créé à chaque client.
        if (!$em->getConnection()->createSchemaManager()->tablesExist(['user'])) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $client;
    }

    private function createUser(string $email, string $nom, string $prenom, ?KernelBrowser $client = null, string $role = User::ROLE_SPORTIF): User
    {
        $user = (new User())
            ->setLastName($nom)
            ->setFirstName($prenom)
            ->setEmail($email)
            ->setPassword('test-hash')
            ->setPhone('0612345678')
            ->setRoles([$role])
            ->setIsVerified(true);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
        if ($client !== null) {
            $this->loginWithJwt($client, $user);
        }

        return $user;
    }
}
