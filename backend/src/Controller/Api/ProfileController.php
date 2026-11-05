<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\UnicodeString;

/**
 * Profil de l'utilisateur connecté : lecture et modification (nom, prénom, téléphone).
 * L'e-mail et le rôle ne sont pas modifiables ici.
 */
#[Route('/api/me/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly UserPresenter $presenter)
    {
    }

    #[Route('', name: 'api_me_profile_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        return $this->json($this->presenter->present($user));
    }

    #[Route('', name: 'api_me_profile_update', methods: ['PUT'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['message' => 'Corps JSON invalide.'], 400);
        }

        $nom = is_string($data['nom'] ?? null) ? trim($data['nom']) : null;
        $prenom = is_string($data['prenom'] ?? null) ? trim($data['prenom']) : null;
        $telephone = is_string($data['telephone'] ?? null) ? trim($data['telephone']) : null;

        $errors = [];
        if ($nom === null || $nom === '' || (new UnicodeString($nom))->length() > 50) {
            $errors['nom'] = 'Le nom est obligatoire et limité à 50 caractères.';
        }
        if ($prenom === null || $prenom === '' || (new UnicodeString($prenom))->length() > 50) {
            $errors['prenom'] = 'Le prénom est obligatoire et limité à 50 caractères.';
        }
        if ($telephone === null || !preg_match('/^\+?[0-9\s().-]{7,20}$/', $telephone)) {
            $errors['telephone'] = 'Saisissez un numéro valide de 7 à 20 caractères.';
        }
        if ($errors !== []) {
            return $this->json(['message' => 'Certains champs sont invalides.', 'errors' => $errors], 422);
        }

        $user->setLastName($nom)->setFirstName($prenom)->setPhone($telephone);
        $entityManager->flush();

        return $this->json($this->presenter->present($user));
    }
}
