<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class ApiLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        // Compte non activé (levé par UserChecker) : message explicite au lieu d'« identifiants incorrects ».
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            return new JsonResponse(['message' => $exception->getMessageKey()], 403);
        }

        return new JsonResponse(['message' => 'Adresse e-mail ou mot de passe incorrect.'], 401);
    }
}
