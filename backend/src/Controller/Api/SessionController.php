<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Session de connexion : connexion (gérée par json_login), utilisateur courant, déconnexion.
 * Remplacée par l'authentification JWT en Phase 3.
 */
final class SessionController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Cette route est traitée par l’authentificateur JSON Symfony.');
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(UserPresenter $presenter): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        return $this->json($presenter->present($user));
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $request->getSession()->invalidate();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
