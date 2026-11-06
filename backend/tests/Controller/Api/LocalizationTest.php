<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * API bilingue : français par défaut, anglais avec « Accept-Language: en » (envoyé par le front).
 * Messages d'erreur et de succès, sécurité (connexion, JWT), e-mails et notifications.
 */
final class LocalizationTest extends ApiTestCase
{
    private const EN = ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9'];
    private const PASSWORD = 'MotDePasse-123!';

    public function testValidationErrorsFollowTheRequestLanguage(): void
    {
        $invalid = ['prenom' => '', 'nom' => 'Test', 'email' => 'invalide', 'telephone' => '0612345678', 'password' => 'court'];

        $this->client->jsonRequest('POST', '/api/inscription', $invalid, self::EN);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Some fields are invalid.', $this->json()['message']);
        self::assertSame('First name is required.', $this->json()['errors']['prenom']);
        self::assertSame('Password must contain at least 8 characters.', $this->json()['errors']['password']);
        self::assertResponseHeaderSame('Content-Language', 'en');

        $this->client->jsonRequest('POST', '/api/inscription', $invalid);
        self::assertSame('Certains champs sont invalides.', $this->json()['message']);
        self::assertSame('Le prénom est obligatoire.', $this->json()['errors']['prenom']);
    }

    public function testApiExceptionMessagesAreTranslated(): void
    {
        $this->client->request('GET', '/api/seances/999999', server: self::EN);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Session not found.', $this->json()['message']);

        $this->client->request('GET', '/api/seances/999999');
        self::assertSame('Séance introuvable.', $this->json()['message']);

        // Toute autre langue que l'anglais retombe sur le français.
        $this->client->request('GET', '/api/seances/999999', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertSame('Séance introuvable.', $this->json()['message']);
    }

    public function testJsonSchemaMessagesWithParametersAreTranslated(): void
    {
        $this->login($this->user('a@example.test'));

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => 'douze'], self::EN);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('This field must be of type integer.', $this->json()['errors']['seanceId']);

        $this->client->jsonRequest('POST', '/api/reservations', ['seanceId' => 'douze']);
        self::assertSame('Ce champ doit être de type nombre entier.', $this->json()['errors']['seanceId']);
    }

    public function testLoginFailureIsTranslatedAndLoginRemembersTheLanguage(): void
    {
        $this->account();

        $this->client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-1'], self::EN);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Incorrect email address or password.', $this->json()['message']);

        $this->client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => 'Mauvais-mdp-1']);
        self::assertSame('Adresse e-mail ou mot de passe incorrect.', $this->json()['message']);

        $this->client->jsonRequest('POST', '/api/login', ['email' => 'camille@example.test', 'password' => self::PASSWORD], self::EN);
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertSame('en', $this->em()->getRepository(User::class)->findOneBy(['email' => 'camille@example.test'])?->getLocale());
    }

    public function testRejectedJwtIsTranslatedAndItsCookieStillCleared(): void
    {
        $this->client->getCookieJar()->set(new Cookie('BEARER', 'abc.def.ghi'));
        $this->client->request('GET', '/api/activites', server: self::EN);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Invalid authentication token. Please log in again.', $this->json()['message']);
        self::assertTrue($this->client->getResponse()->headers->getCookies()[0]->isCleared());

        $this->client->getCookieJar()->set(new Cookie('BEARER', 'abc.def.ghi'));
        $this->client->request('GET', '/api/activites');
        self::assertSame('Jeton d’authentification invalide. Reconnectez-vous.', $this->json()['message']);
    }

    public function testRegistrationInEnglishStoresTheLocaleAndSendsAnEnglishEmail(): void
    {
        $this->client->jsonRequest('POST', '/api/inscription', [
            'prenom' => 'Camille',
            'nom' => 'Test',
            'email' => 'camille@example.test',
            'telephone' => '+33123456789',
            'password' => self::PASSWORD,
        ], self::EN);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Account created successfully. You can now log in.', $this->json()['message']);
        self::assertSame('en', $this->em()->getRepository(User::class)->findOneBy(['email' => 'camille@example.test'])?->getLocale());

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('Welcome to FitBook', $email->getSubject());
        self::assertStringContainsString('Hello Camille', (string) $email->getHtmlBody());
        self::assertStringContainsString('Move more. Live better.', (string) $email->getHtmlBody());
        self::assertStringContainsString('lang="en"', (string) $email->getHtmlBody());
    }

    public function testNotificationsAndEmailsUseTheRecipientLanguage(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $session = $this->session($this->activity(), $this->gym($manager), null, title: 'Yoga du soir');
        $athlete = $this->user('a@example.test');
        $this->reload($athlete)->setLocale('en');
        $this->em()->flush();
        $this->login($athlete);

        // Requête en français : la notification et l'e-mail suivent la langue du compte.
        $this->send('POST', '/api/reservations', ['seanceId' => $session->getId()]);
        self::assertResponseStatusCodeSame(201);

        $notification = $this->em()->getRepository(Notification::class)->findOneBy([]);
        self::assertStringStartsWith('Booking confirmed: Yoga du soir on ', (string) $notification?->getMessage());
        self::assertSame('Booking confirmed on FitBook', self::getMailerMessage()?->getSubject());
    }

    private function account(): void
    {
        $user = $this->user('camille@example.test');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->em()->flush();
    }
}
