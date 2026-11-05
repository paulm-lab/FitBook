<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\UnicodeString;

#[Route('/api/me/profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'api_me_profile_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        return $this->json($this->profileData($user));
    }

    #[Route('', name: 'api_me_profile_update', methods: ['PUT'])]
    public function update(Request $request, \Doctrine\ORM\EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        try {
            $data = $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return $this->json(['message' => 'Corps JSON invalide.'], 400);
        }

        $errors = [];
        $nomValue = $data['nom'] ?? null;
        $prenomValue = $data['prenom'] ?? null;
        $telephoneValue = $data['telephone'] ?? null;
        $nom = is_string($nomValue) ? trim($nomValue) : '';
        $prenom = is_string($prenomValue) ? trim($prenomValue) : '';
        $telephone = is_string($telephoneValue) ? trim($telephoneValue) : '';
        if (!is_string($nomValue) || $nom === '' || (new UnicodeString($nom))->length() > 50) $errors['nom'] = 'Le nom est obligatoire et limité à 50 caractères.';
        if (!is_string($prenomValue) || $prenom === '' || (new UnicodeString($prenom))->length() > 50) $errors['prenom'] = 'Le prénom est obligatoire et limité à 50 caractères.';
        if (!is_string($telephoneValue) || strlen($telephone) < 7 || strlen($telephone) > 20 || !preg_match('/^\+?[0-9\s().-]{7,20}$/', $telephone)) $errors['telephone'] = 'Saisissez un numéro valide de 7 à 20 caractères.';
        if ($errors !== []) return $this->json(['message' => 'Certains champs sont invalides.', 'errors' => $errors], 422);

        $user->setNom($nom)->setPrenom($prenom)->setTelephone($telephone);
        $entityManager->flush();

        return $this->json($this->profileData($user));
    }

    private function profileData(Utilisateur $user): array
    {
        return [
            'id' => $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'telephone' => $user->getTelephone(),
        ];
    }
}
