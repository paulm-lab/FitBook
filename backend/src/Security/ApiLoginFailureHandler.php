<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Réponse JSON d'une connexion refusée.
 *
 * - 429 : trop de tentatives (login_throttling) ;
 * - 403 : compte non vérifié ou bloqué (UserChecker), message explicite
 *   (+ code « email_non_verifie » pour une adresse non confirmée) ;
 * - 401 : identifiants incorrects, même message que l'adresse existe ou non.
 */
final class ApiLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(['message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.'], 429);
        }

        // Vérifié après le mot de passe : l'état du compte n'est révélé qu'à son propriétaire.
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            $body = ['message' => $exception->getMessageKey()];
            // Code stable, indépendant de la langue : le front propose alors de renvoyer le lien.
            if ($exception->getMessageKey() === UserChecker::UNVERIFIED_MESSAGE) {
                $body['code'] = 'email_non_verifie';
            }

            return new JsonResponse($body, 403);
        }

        return new JsonResponse(['message' => 'Adresse e-mail ou mot de passe incorrect.'], 401);
    }
}
