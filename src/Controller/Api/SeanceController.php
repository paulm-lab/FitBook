<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Seance;
use App\Repository\SeanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seances')]
final class SeanceController extends AbstractController
{
    #[Route('', name: 'api_seances_index', methods: ['GET'])]
    public function index(Request $request, SeanceRepository $repository): JsonResponse
    {
        $query = $repository->createQueryBuilder('seance')
            ->leftJoin('seance.activite', 'activite')->addSelect('activite')
            ->leftJoin('seance.coach', 'coach')->addSelect('coach')
            ->leftJoin('seance.salle', 'salle')->addSelect('salle')
            ->andWhere('seance.statut = :status')->setParameter('status', 'actif')
            ->andWhere('seance.dateDebut >= :now')->setParameter('now', new \DateTimeImmutable());

        if ($activity = trim((string) $request->query->get('activite', ''))) {
            $query->andWhere('LOWER(activite.nom) LIKE :activity')
                ->setParameter('activity', '%' . mb_strtolower($activity) . '%');
        }
        if ($place = trim((string) $request->query->get('lieu', ''))) {
            $query->andWhere('LOWER(salle.adresse) LIKE :place')
                ->setParameter('place', '%' . mb_strtolower($place) . '%');
        }
        if ($date = $request->query->get('date')) {
            $dateValue = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
            if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
                return $this->json(['message' => 'Le filtre date doit être au format YYYY-MM-DD.'], 400);
            }
            $query->andWhere('seance.dateSeance = :date')->setParameter('date', $dateValue);
        }

        $items = $query->orderBy('seance.dateSeance', 'ASC')->addOrderBy('seance.heureDebut', 'ASC')->getQuery()->getResult();
        return $this->json(array_map(fn (Seance $item): array => $this->seanceData($item), $items));
    }

    #[Route('/{id}', name: 'api_seances_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, SeanceRepository $repository): JsonResponse
    {
        $item = $repository->find($id);
        return $item ? $this->json($this->seanceData($item)) : $this->json(['message' => 'Séance introuvable.'], 404);
    }

    private function seanceData(Seance $item): array
    {
        $coach = $item->getCoach();
        $salle = $item->getSalle();
        $activite = $item->getActivite();

        return [
            'id' => $item->getId(),
            'titre' => $item->getTitre(),
            'description' => $item->getDescription(),
            'dateDebut' => $item->getDateDebut()?->format('Y-m-d H:i:s'),
            'dateFin' => $item->getDateFin()?->format('Y-m-d H:i:s'),
            'dateSeance' => $item->getDateSeance()?->format('Y-m-d'),
            'heureDebut' => $item->getHeureDebut()?->format('H:i:s'),
            'heureFin' => $item->getHeureFin()?->format('H:i:s'),
            'capaciteMax' => $item->getCapaciteMax(),
            'placesDisponibles' => $item->getPlacesDisponibles(),
            'prix' => $item->getPrix(),
            'statut' => $item->getStatut(),
            'activite' => $activite ? ['id' => $activite->getId(), 'nom' => $activite->getNom()] : null,
            'coach' => $coach ? ['id' => $coach->getId(), 'nom' => $coach->getNom(), 'prenom' => $coach->getPrenom()] : null,
            'salle' => $salle ? ['id' => $salle->getId(), 'nom' => $salle->getNom(), 'adresse' => $salle->getAdresse()] : null,
        ];
    }
}
