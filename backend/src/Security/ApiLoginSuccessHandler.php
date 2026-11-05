<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\UserPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Réponse JSON d'une connexion réussie : le profil de l'utilisateur connecté.
 */
final class ApiLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly UserPresenter $presenter)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur invalide.'], 401);
        }

        return new JsonResponse($this->presenter->present($user));
    }
}
