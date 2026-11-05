<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Authentifie un client de test comme le ferait le front : avec un vrai jeton JWT envoyé
 * dans l'en-tête Authorization de toutes les requêtes suivantes.
 *
 * (KernelBrowser::loginUser() ne vaut que pour une requête sur un pare-feu sans état.)
 */
trait JwtLoginTrait
{
    protected function loginWithJwt(KernelBrowser $client, User $user): string
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $token;
    }
}
