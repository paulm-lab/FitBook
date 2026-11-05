<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Connexion JWT : connexion (traitée par json_login + Lexik), utilisateur courant, déconnexion.
 */
final class SessionController extends AbstractController
{
    /** Nom du cookie où Lexik dépose le jeton (lexik_jwt_authentication.yaml). */
    private const TOKEN_COOKIE = 'BEARER';

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

    /**
     * Supprime le cookie du jeton. Un JWT reste valable jusqu'à son expiration (JWT_TTL) :
     * un client qui l'a copié ailleurs doit simplement l'oublier.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        $response->headers->clearCookie(self::TOKEN_COOKIE, '/', null, false, true, Cookie::SAMESITE_LAX);

        return $response;
    }
}
