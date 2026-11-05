<?php

namespace App\Controller\Api;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/utilisateurs')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'api_utilisateurs_index', methods: ['GET'])]
    public function index(UtilisateurRepository $repository): JsonResponse
    {
        $utilisateurs = $repository->findAll();

        $data = [];

        foreach ($utilisateurs as $utilisateur) {
            $data[] = [
                'id' => $utilisateur->getId(),
                'nom' => $utilisateur->getNom(),
                'prenom' => $utilisateur->getPrenom(),
                'email' => $utilisateur->getEmail(),
                'telephone' => $utilisateur->getTelephone(),
                'role' => $utilisateur->getRole(),
            ];
        }

        return $this->json($data);
    }
}


