<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\FitBookMailer;
use App\Service\MailQueueTracker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationTest extends WebTestCase
{
    private const PASSWORD = 'MotDePasse-123!';

    public function testRegistrationPersistsAnInactiveUserWithHashedPassword(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        self::assertResponseStatusCodeSame(201);
        $response = $this->json($client);
        self::assertSame('camille@example.test', $response['email']);
        self::assertTrue($response['verificationEmailQueued']);
        self::assertSame('queued', $response['verificationEmailStatus']);
        self::assertStringContainsString("en file d'attente", $response['message']);

        $user = $this->findUser('camille@example.test');
        self::assertNotNull($user);
        self::assertSame('Camille', $user->getPrenom());
        self::assertSame('Test', $user->getNom());
        self::assertSame('+33123456789', $user->getTelephone());
        self::assertSame('inactif', $user->getStatut());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertNotNull($user->getVerificationTokenHash());
        self::assertQueuedEmailCount(1);
    }

    public function testRegistrationReportsAnEmailAcceptedSynchronouslyAsSent(): void
    {
        $client = $this->clientWithSchema();
        $this->useMailer(new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
            }
        });

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        self::assertResponseStatusCodeSame(201);
        $response = $this->json($client);
        self::assertSame('sent', $response['verificationEmailStatus']);
        self::assertStringContainsString('a été envoyé', $response['message']);
    }

    public function testSmtpFailureStillCreatesTheAccountAndAllowsAResend(): void
    {
        $client = $this->clientWithSchema();
        $this->useMailer(new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('535 5.7.0 The email limit is reached');
            }
        });

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        self::assertResponseStatusCodeSame(201);
        $response = $this->json($client);
        self::assertSame('failed', $response['verificationEmailStatus']);
        self::assertFalse($response['verificationEmailQueued']);
        self::assertStringContainsString('demander un nouveau lien', $response['message']);
        $user = $this->findUser('camille@example.test');
        self::assertSame('inactif', $user->getStatut());
        self::assertNotNull($user->getVerificationTokenHash());

        $client->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'camille@example.test']);
        self::assertResponseStatusCodeSame(503);
    }

    public function testRegistrationNormalizesEmailAndRejectsDuplicates(): void
    {
        $client = $this->clientWithSchema();
        $client->jsonRequest('POST', '/api/inscription', $this->registrationData(['email' => '  Camille@Example.TEST ']));
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        self::assertResponseStatusCodeSame(409);
        self::assertSame('Cette adresse e-mail est déjà utilisée.', $this->json($client)['errors']['email']);
    }

    public function testRegistrationRejectsInvalidFieldsWithFrenchMessages(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', ['prenom' => '', 'nom' => 'Test', 'email' => 'invalide', 'telephone' => 'abc', 'password' => 'court']);

        self::assertResponseStatusCodeSame(400);
        $response = $this->json($client);
        self::assertSame('Certains champs sont invalides.', $response['message']);
        self::assertSame(['prenom', 'email', 'telephone', 'password'], array_keys($response['errors']));
        self::assertNull($this->findUser('invalide'));
    }

    public function testRegistrationRejectsMalformedJson(): void
    {
        $client = $this->clientWithSchema();

        $client->request('POST', '/api/inscription', server: ['CONTENT_TYPE' => 'application/json'], content: '{pas du json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnverifiedAccountCannotLogIn(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('inactif');

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Confirmez votre adresse e-mail avant de vous connecter.', $this->json($client)['message']);
    }

    public function testUnverifiedStatusIsNotRevealedWithAWrongPassword(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('inactif');

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-1']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Adresse e-mail ou mot de passe incorrect.', $this->json($client)['message']);
    }

    public function testEmailVerificationActivatesTheAccount(): void
    {
        $client = $this->clientWithSchema();
        $user = $this->createUser('inactif');
        $token = str_repeat('ab', 32);
        $user->setVerificationTokenHash(hash('sha256', $token))->setVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->entityManager()->flush();

        $client->request('GET', '/api/auth/verify-email?token=' . $token);

        self::assertResponseRedirects('http://localhost:4200/verification-email?status=success');
        $user = $this->findUser('camille@example.test');
        self::assertSame('actif', $user->getStatut());
        self::assertNotNull($user->getEmailVerifiedAt());
        self::assertNull($user->getVerificationTokenHash());
    }

    public function testExpiredOrUnknownVerificationTokenIsRejected(): void
    {
        $client = $this->clientWithSchema();
        $user = $this->createUser('inactif');
        $token = str_repeat('cd', 32);
        $user->setVerificationTokenHash(hash('sha256', $token))->setVerificationTokenExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->entityManager()->flush();

        $client->request('GET', '/api/auth/verify-email?token=' . $token);
        self::assertResponseRedirects('http://localhost:4200/verification-email?status=error');

        $client->request('GET', '/api/auth/verify-email?token=' . str_repeat('0', 64));
        self::assertResponseRedirects('http://localhost:4200/verification-email?status=error');
        self::assertSame('inactif', $this->findUser('camille@example.test')->getStatut());
    }

    public function testActiveAccountLogsInAndKeepsItsSessionUntilLogout(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('actif');

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
        self::assertSame('Camille', $this->json($client)['prenom']);
        self::assertArrayNotHasKey('motDePasse', $this->json($client));

        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame('camille@example.test', $this->json($client)['email']);

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testResendVerificationQueuesANewLinkForInactiveAccounts(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser('inactif');

        $client->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'camille@example.test']);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->findUser('camille@example.test')->getVerificationTokenHash());
        self::assertQueuedEmailCount(1);
    }

    private function clientWithSchema(): KernelBrowser
    {
        $client = static::createClient();
        // La base SQLite en mémoire doit survivre entre les requêtes d'un même test.
        $client->disableReboot();
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Utilisateur::class)]);

        return $client;
    }

    private function useMailer(MailerInterface $mailer): void
    {
        $container = static::getContainer();
        $container->set(FitBookMailer::class, new FitBookMailer($mailer, $container->get(MailQueueTracker::class), 'no-reply@fitbook.test'));
    }

    private function createUser(string $statut): Utilisateur
    {
        $user = (new Utilisateur())
            ->setNom('Test')
            ->setPrenom('Camille')
            ->setEmail('camille@example.test')
            ->setTelephone('0612345678')
            ->setRole('ROLE_USER')
            ->setDateInscription(new \DateTimeImmutable('today'))
            ->setStatut($statut);
        $user->setMotDePasse(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    /** @param array<string, string> $overrides */
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'prenom' => 'Camille',
            'nom' => 'Test',
            'email' => 'camille@example.test',
            'telephone' => '+33123456789',
            'password' => self::PASSWORD,
        ], $overrides);
    }

    private function findUser(string $email): ?Utilisateur
    {
        $this->entityManager()->clear();

        return static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => $email]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array<string, mixed> */
    private function json(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
