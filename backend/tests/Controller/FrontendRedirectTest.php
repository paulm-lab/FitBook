<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FrontendRedirectTest extends WebTestCase
{
    public function testFrontendPageOpenedOnTheBackendRedirectsToAngular(): void
    {
        $client = static::createClient();

        $client->request('GET', '/inscription');

        self::assertResponseRedirects('http://localhost:4200/inscription');
    }

    public function testQueryStringAndRootAreKept(): void
    {
        $client = static::createClient();

        $client->request('GET', '/recherche?q=yoga');
        self::assertResponseRedirects('http://localhost:4200/recherche?q=yoga');

        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost:4200/');
    }

    public function testApiRoutesAreNeverRedirected(): void
    {
        $client = static::createClient();

        // L'inscription reste une API POST : un GET ne doit pas être détourné vers le front.
        $client->request('GET', '/api/inscription');
        self::assertResponseStatusCodeSame(405);

        $client->request('GET', '/api/route-inexistante');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyGetIsRedirected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/inscription');

        self::assertResponseStatusCodeSame(405);
    }
}
