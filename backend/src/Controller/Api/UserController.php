<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liste des comptes, réservée à l'administrateur (CRUD complet en Phase 4).
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/api/utilisateurs')]
final class UserController extends AbstractController
{
    #[Route('', name: 'api_utilisateurs_index', methods: ['GET'])]
    public function index(UserRepository $users, UserPresenter $presenter): JsonResponse
    {
        return $this->json(array_map(
            static fn (User $user): array => $presenter->present($user) + [
                'estVerifie' => $user->isVerified(),
                'estBloque' => $user->isBlocked(),
            ],
            $users->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']),
        ));
    }
}
