<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Activite;
use App\Entity\Coach;
use App\Entity\Salle;
use App\Repository\ActiviteRepository;
use App\Repository\CoachRepository;
use App\Repository\SalleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class CataloguesController extends AbstractController
{
    #[Route('/activites', name: 'api_activites_index', methods: ['GET'])]
    public function activites(ActiviteRepository $repository): JsonResponse
    {
        return $this->json(array_map(fn (Activite $item): array => $this->activiteData($item), $repository->findBy(['statut' => 'actif'], ['nom' => 'ASC'])));
    }

    #[Route('/activites/{id}', name: 'api_activites_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function activite(int $id, ActiviteRepository $repository): JsonResponse
    {
        $item = $repository->find($id);
        return $item && $item->getStatut() === 'actif' ? $this->json($this->activiteData($item)) : $this->json(['message' => 'Activité introuvable.'], 404);
    }

    #[Route('/coaches', name: 'api_coaches_index', methods: ['GET'])]
    public function coaches(CoachRepository $repository): JsonResponse
    {
        return $this->json(array_map(fn (Coach $item): array => $this->coachData($item), $repository->findBy(['statut' => 'actif'], ['nom' => 'ASC'])));
    }

    #[Route('/coaches/{id}', name: 'api_coaches_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function coach(int $id, CoachRepository $repository): JsonResponse
    {
        $item = $repository->find($id);
        return $item && $item->getStatut() === 'actif' ? $this->json($this->coachData($item)) : $this->json(['message' => 'Coach introuvable.'], 404);
    }

    #[Route('/salles', name: 'api_salles_index', methods: ['GET'])]
    public function salles(SalleRepository $repository): JsonResponse
    {
        return $this->json(array_map(fn (Salle $item): array => $this->salleData($item), $repository->findBy(['statut' => 'actif'], ['nom' => 'ASC'])));
    }

    #[Route('/salles/{id}', name: 'api_salles_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function salle(int $id, SalleRepository $repository): JsonResponse
    {
        $item = $repository->find($id);
        return $item && $item->getStatut() === 'actif' ? $this->json($this->salleData($item)) : $this->json(['message' => 'Salle introuvable.'], 404);
    }

    private function activiteData(Activite $item): array
    {
        return ['id' => $item->getId(), 'nom' => $item->getNom(), 'description' => $item->getDescription(), 'duree' => $item->getDuree(), 'niveau' => $item->getNiveau(), 'image' => $item->getImage()];
    }

    private function coachData(Coach $item): array
    {
        return ['id' => $item->getId(), 'nom' => $item->getNom(), 'prenom' => $item->getPrenom(), 'specialite' => $item->getSpecialite(), 'description' => $item->getDescription(), 'biographie' => $item->getDescription(), 'experience' => $item->getExperience(), 'certification' => $item->getCertification(), 'tarifHoraire' => $item->getTarifHoraire(), 'photo' => $item->getPhoto()];
    }

    private function salleData(Salle $item): array
    {
        return ['id' => $item->getId(), 'nom' => $item->getNom(), 'adresse' => $item->getAdresse(), 'capacite' => $item->getCapacite(), 'description' => $item->getDescription(), 'equipements' => $item->getEquipements(), 'image' => $item->getImage()];
    }
}