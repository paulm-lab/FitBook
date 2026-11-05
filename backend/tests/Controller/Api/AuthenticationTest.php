<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FitBookMailer;
use App\Service\MailQueueTracker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription, confirmation de l'adresse par lien signé, connexion et déconnexion.
 */
final class AuthenticationTest extends WebTestCase
{
    private const PASSWORD = 'MotDePasse-123!';

    public function testRegistrationPersistsAnUnverifiedSportifWithHashedPassword(): void
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
        self::assertSame('Camille', $user->getFirstName());
        self::assertSame('Test', $user->getLastName());
        self::assertSame('+33123456789', $user->getPhone());
        self::assertSame([User::ROLE_SPORTIF], $user->getRoles());
        self::assertFalse($user->isVerified());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertQueuedEmailCount(1);
        self::assertStringContainsString('_hash=', $this->verificationLinkFromEmail());
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
        self::assertFalse($this->findUser('camille@example.test')->isVerified());

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
        $this->createUser(verified: false);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Confirmez votre adresse e-mail avant de vous connecter.', $this->json($client)['message']);
    }

    public function testBlockedAccountCannotLogIn(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true, blocked: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('bloqué', $this->json($client)['message']);
    }

    public function testAccountStatusIsNotRevealedWithAWrongPassword(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: false);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-1']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Adresse e-mail ou mot de passe incorrect.', $this->json($client)['message']);
    }

    public function testSignedLinkFromTheEmailActivatesTheAccount(): void
    {
        $client = $this->clientWithSchema();
        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        $client->request('GET', $this->verificationLinkFromEmail());

        self::assertResponseRedirects('http://localhost:4200/verification-email?status=success');
        self::assertTrue($this->findUser('camille@example.test')->isVerified());

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testTamperedOrExpiredLinksAreRejected(): void
    {
        $client = $this->clientWithSchema();
        $user = $this->createUser(verified: false);
        $signer = static::getContainer()->get(UriSigner::class);
        $base = sprintf('http://localhost/api/auth/verify-email?email=%s&id=%d', hash('sha256', (string) $user->getEmail()), $user->getId());

        // Lien modifié après signature : un autre identifiant.
        $tampered = str_replace('id=' . $user->getId(), 'id=999', $signer->sign($base, new \DateTimeImmutable('+1 hour')));
        $client->request('GET', $tampered);
        self::assertResponseRedirects('http://localhost:4200/verification-email?status=error');

        // Lien expiré.
        $client->request('GET', $signer->sign($base, new \DateTimeImmutable('-1 minute')));
        self::assertResponseRedirects('http://localhost:4200/verification-email?status=error');

        // Lien sans signature.
        $client->request('GET', $base);
        self::assertResponseRedirects('http://localhost:4200/verification-email?status=error');

        self::assertFalse($this->findUser('camille@example.test')->isVerified());
    }

    public function testActiveAccountLogsInAndKeepsItsSessionUntilLogout(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
        self::assertSame('Camille', $this->json($client)['prenom']);
        self::assertArrayNotHasKey('password', $this->json($client));

        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame('camille@example.test', $this->json($client)['email']);

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testResendVerificationQueuesANewLinkForUnverifiedAccounts(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: false);

        $client->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'camille@example.test']);

        self::assertResponseIsSuccessful();
        self::assertQueuedEmailCount(1);
    }

    public function testResendGivesTheSameAnswerForUnknownAddresses(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'inconnu@example.test']);

        self::assertResponseIsSuccessful();
        self::assertQueuedEmailCount(0);
    }

    private function clientWithSchema(): KernelBrowser
    {
        $client = static::createClient();
        // La base SQLite en mémoire doit survivre entre les requêtes d'un même test.
        $client->disableReboot();
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $client;
    }

    private function useMailer(MailerInterface $mailer): void
    {
        $container = static::getContainer();
        $container->set(FitBookMailer::class, new FitBookMailer($mailer, $container->get(MailQueueTracker::class), 'no-reply@fitbook.test'));
    }

    /**
     * Lien de confirmation lu dans le HTML de l'e-mail mis en file, tel que l'utilisateur le reçoit.
     * (Symfony vide le contexte Twig une fois l'e-mail rendu : on lit donc le corps HTML.)
     */
    private function verificationLinkFromEmail(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertMatchesRegularExpression('#href="([^"]*verify-email[^"]*)"#', (string) $email->getHtmlBody());
        preg_match('#href="([^"]*verify-email[^"]*)"#', (string) $email->getHtmlBody(), $match);

        return html_entity_decode($match[1]);
    }

    private function createUser(bool $verified, bool $blocked = false): User
    {
        $user = (new User())
            ->setLastName('Test')
            ->setFirstName('Camille')
            ->setEmail('camille@example.test')
            ->setPhone('0612345678')
            ->setRoles([User::ROLE_SPORTIF])
            ->setIsVerified($verified)
            ->setIsBlocked($blocked);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
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

    private function findUser(string $email): ?User
    {
        $this->entityManager()->clear();

        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
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
