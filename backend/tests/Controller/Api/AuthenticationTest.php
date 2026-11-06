<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\CoachProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FitBookMailer;
use App\Service\MailQueueTracker;
use App\Tests\Support\JwtLoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Authentification (Phase 3) : inscription avec rôle (compte actif immédiatement),
 * connexion JWT (en-tête et cookie), limitation des tentatives, mot de passe oublié.
 */
final class AuthenticationTest extends WebTestCase
{
    use JwtLoginTrait;

    private const PASSWORD = 'MotDePasse-123!';

    // -------------------------------------------------------------------------
    // Inscription
    // -------------------------------------------------------------------------

    public function testRegistrationPersistsAnActiveSportifWithHashedPassword(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());

        self::assertResponseStatusCodeSame(201);
        $response = $this->json($client);
        self::assertSame('camille@example.test', $response['email']);
        self::assertSame('sportif', $response['role']);
        self::assertSame('Compte créé avec succès. Vous pouvez maintenant vous connecter.', $response['message']);
        self::assertArrayNotHasKey('verificationEmailStatus', $response);

        $user = $this->findUser('camille@example.test');
        self::assertSame('Camille', $user->getFirstName());
        self::assertSame('+33123456789', $user->getPhone());
        self::assertSame([User::ROLE_SPORTIF], $user->getRoles());
        self::assertTrue($user->isVerified());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::PASSWORD));
        self::assertEmailCount(1);
    }

    public function testNewAccountCanLogInImmediatelyAndReachItsProfile(): void
    {
        $client = $this->clientWithSchema();
        $client->jsonRequest('POST', '/api/inscription', $this->registrationData());
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
        $token = $this->json($client)['token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame('camille@example.test', $this->json($client)['email']);
    }

    public function testCoachRegistrationCreatesAnEmptyCoachProfile(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData(['role' => 'coach']));

        self::assertResponseStatusCodeSame(201);
        $user = $this->findUser('camille@example.test');
        self::assertSame([User::ROLE_COACH], $user->getRoles());
        self::assertInstanceOf(CoachProfile::class, $user->getCoachProfile());
    }

    public function testManagerRoleCanBeChosenButNeverAdministrator(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData(['role' => 'gestionnaire']));
        self::assertResponseStatusCodeSame(201);
        self::assertSame([User::ROLE_GESTIONNAIRE], $this->findUser('camille@example.test')->getRoles());

        $client->jsonRequest('POST', '/api/inscription', $this->registrationData(['email' => 'pirate@example.test', 'role' => 'admin']));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('role', $this->json($client)['errors']);
        self::assertNull($this->findUser('pirate@example.test'));
    }

    public function testSmtpFailureDoesNotPreventRegistrationNorLogin(): void
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

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
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

    public function testInvalidFieldsGet422WithOneFrenchMessagePerField(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/inscription', ['prenom' => '', 'nom' => 'Test', 'email' => 'invalide', 'telephone' => 'abc', 'password' => 'court']);

        self::assertResponseStatusCodeSame(422);
        $response = $this->json($client);
        self::assertSame('Certains champs sont invalides.', $response['message']);
        self::assertSame(['prenom', 'email', 'telephone', 'password'], array_keys($response['errors']));
        self::assertSame('Le prénom est obligatoire.', $response['errors']['prenom']);
        self::assertNull($this->findUser('invalide'));
    }

    public function testRegistrationRejectsMalformedJson(): void
    {
        $client = $this->clientWithSchema();

        $client->request('POST', '/api/inscription', server: ['CONTENT_TYPE' => 'application/json'], content: '{pas du json');

        self::assertResponseStatusCodeSame(400);
    }

    // -------------------------------------------------------------------------
    // Connexion JWT
    // -------------------------------------------------------------------------

    public function testLoginReturnsAJwtAndSetsAnHttpOnlyCookie(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);

        self::assertResponseIsSuccessful();
        $response = $this->json($client);
        self::assertMatchesRegularExpression('/^[\w-]+\.[\w-]+\.[\w-]+$/', $response['token'], 'Un JWT a trois segments.');
        self::assertSame('Camille', $response['prenom']);
        self::assertArrayNotHasKey('password', $response);

        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertSame('BEARER', $cookie?->getName());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
    }

    public function testJwtInAuthorizationHeaderAuthenticatesRequests(): void
    {
        $client = $this->clientWithSchema();
        $this->loginWithJwt($client, $this->createUser(verified: true));

        $client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame('camille@example.test', $this->json($client)['email']);
    }

    public function testCookieSessionWorksUntilLogout(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testForgedTokenIsRejected(): void
    {
        $client = $this->clientWithSchema();
        $token = $this->loginWithJwt($client, $this->createUser(verified: true));

        // Charge utile modifiée : la signature RSA ne correspond plus.
        [$header, , $signature] = explode('.', $token);
        $payload = rtrim(strtr(base64_encode('{"username":"admin@example.test","roles":["ROLE_ADMIN"]}'), '+/', '-_'), '=');
        $client->setServerParameter('HTTP_AUTHORIZATION', "Bearer {$header}.{$payload}.{$signature}");
        $client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectedCookieIsClearedByThe401Response(): void
    {
        $client = $this->clientWithSchema();

        $client->getCookieJar()->set(new Cookie('BEARER', 'abc.def.ghi'));
        $client->request('GET', '/api/activites');

        self::assertResponseStatusCodeSame(401);
        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertSame('BEARER', $cookie?->getName());
        self::assertTrue($cookie->isCleared());
    }

    public function testBlockedAccountCannotLogIn(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true, blocked: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('bloqué', $this->json($client)['message']);
    }

    public function testBlockingAnAccountInvalidatesItsExistingToken(): void
    {
        $client = $this->clientWithSchema();
        $this->loginWithJwt($client, $this->createUser(verified: true));

        $this->findUser('camille@example.test')->setIsBlocked(true);
        $this->entityManager()->flush();
        $client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAccountStatusIsNotRevealedWithAWrongPassword(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true, blocked: true);

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-1']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Adresse e-mail ou mot de passe incorrect.', $this->json($client)['message']);
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true);

        for ($i = 1; $i <= 5; ++$i) {
            $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-' . $i]);
            self::assertResponseStatusCodeSame(401);
        }

        // Même le bon mot de passe est refusé pendant le blocage.
        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(429);
    }

    // -------------------------------------------------------------------------
    // Mot de passe oublié (ResetPasswordBundle)
    // -------------------------------------------------------------------------

    public function testForgottenPasswordCanBeResetOnceWithTheEmailedToken(): void
    {
        $client = $this->clientWithSchema();
        $this->createUser(verified: true);

        $client->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'camille@example.test']);
        self::assertResponseIsSuccessful();
        $link = $this->linkFromEmail('reinitialiser-mot-de-passe');
        self::assertStringStartsWith('http://localhost:4200/reinitialiser-mot-de-passe?token=', $link);
        parse_str((string) parse_url($link, \PHP_URL_QUERY), $query);

        $client->jsonRequest('POST', '/api/auth/reset-password', ['token' => $query['token'], 'password' => 'Nouveau-mdp-456']);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Nouveau-mdp-456']);
        self::assertResponseIsSuccessful();

        // Usage unique : le même jeton ne sert pas deux fois.
        $client->jsonRequest('POST', '/api/auth/reset-password', ['token' => $query['token'], 'password' => 'Encore-un-789']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testForgottenPasswordAnswersTheSameForUnknownAddresses(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'inconnu@example.test']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Si un compte correspond', $this->json($client)['message']);
        self::assertEmailCount(0);
    }

    public function testResetRejectsInvalidTokensAndShortPasswords(): void
    {
        $client = $this->clientWithSchema();

        $client->jsonRequest('POST', '/api/auth/reset-password', ['token' => 'jeton-invente', 'password' => 'Nouveau-mdp-456']);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('POST', '/api/auth/reset-password', ['token' => 'jeton-invente', 'password' => 'court']);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', $this->json($client)['errors']);
    }

    // -------------------------------------------------------------------------
    // En-tête Host
    // -------------------------------------------------------------------------

    public function testUntrustedHostHeaderIsRejected(): void
    {
        $client = $this->clientWithSchema();

        // Empêche de faire générer des liens d'e-mail pointant vers un domaine piégé.
        $client->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'camille@example.test'], ['HTTP_HOST' => 'site-pirate.example']);

        self::assertResponseStatusCodeSame(400);
    }

    // -------------------------------------------------------------------------
    // Outils
    // -------------------------------------------------------------------------

    private function clientWithSchema(): KernelBrowser
    {
        // Request::create() annonce « Accept-Language: en-us » par défaut : le front envoie toujours la langue.
        $client = static::createClient(server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        // La base SQLite en mémoire doit survivre entre les requêtes d'un même test.
        $client->disableReboot();
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $client;
    }

    private function useMailer(MailerInterface $mailer): void
    {
        $container = static::getContainer();
        $container->set(FitBookMailer::class, new FitBookMailer($mailer, $container->get(MailQueueTracker::class), 'no-reply@fitbook.test', $container->get(TranslatorInterface::class)));
    }

    /**
     * Lien lu dans le HTML du dernier e-mail mis en file, tel que l'utilisateur le reçoit.
     * (Symfony vide le contexte Twig une fois l'e-mail rendu : on lit donc le corps HTML.)
     */
    private function linkFromEmail(string $pathFragment): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $pattern = '#href="([^"]*' . preg_quote($pathFragment, '#') . '[^"]*)"#';
        self::assertMatchesRegularExpression($pattern, (string) $email->getHtmlBody());
        preg_match($pattern, (string) $email->getHtmlBody(), $match);

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
