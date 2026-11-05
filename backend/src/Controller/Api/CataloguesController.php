<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Activity;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Repository\ActivityRepository;
use App\Repository\CoachProfileRepository;
use App\Repository\GymRepository;
use App\Service\CataloguePresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue public en lecture : activités, coachs, salles (CRUD en Phase 4).
 */
#[Route('/api')]
final class CataloguesController extends AbstractController
{
    public function __construct(private readonly CataloguePresenter $presenter)
    {
    }

    #[Route('/activites', name: 'api_activites_index', methods: ['GET'])]
    public function activites(ActivityRepository $repository): JsonResponse
    {
        return $this->json(array_map(
            fn (Activity $item): array => $this->presenter->activity($item),
            $repository->findBy([], ['name' => 'ASC']),
        ));
    }

    #[Route('/activites/{id}', name: 'api_activites_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function activite(int $id, ActivityRepository $repository): JsonResponse
    {
        $item = $repository->find($id);

        return $item
            ? $this->json($this->presenter->activity($item))
            : $this->json(['message' => 'Activité introuvable.'], 404);
    }

    /** Seuls les coachs dont le compte est vérifié et non bloqué sont publiés. */
    #[Route('/coaches', name: 'api_coaches_index', methods: ['GET'])]
    public function coaches(CoachProfileRepository $repository): JsonResponse
    {
        $coaches = $repository->createQueryBuilder('c')
            ->join('c.user', 'u')->addSelect('u')
            ->andWhere('u.isVerified = true AND u.isBlocked = false')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map(fn (CoachProfile $item): array => $this->presenter->coach($item), $coaches));
    }

    #[Route('/coaches/{id}', name: 'api_coaches_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function coach(int $id, CoachProfileRepository $repository): JsonResponse
    {
        $item = $repository->find($id);
        $user = $item?->getUser();

        return $item && $user?->isVerified() && !$user->isBlocked()
            ? $this->json($this->presenter->coach($item))
            : $this->json(['message' => 'Coach introuvable.'], 404);
    }

    #[Route('/salles', name: 'api_salles_index', methods: ['GET'])]
    public function salles(GymRepository $repository): JsonResponse
    {
        return $this->json(array_map(
            fn (Gym $item): array => $this->presenter->gym($item),
            $repository->findBy([], ['name' => 'ASC']),
        ));
    }

    #[Route('/salles/{id}', name: 'api_salles_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function salle(int $id, GymRepository $repository): JsonResponse
    {
        $item = $repository->find($id);

        return $item
            ? $this->json($this->presenter->gym($item))
            : $this->json(['message' => 'Salle introuvable.'], 404);
    }
}
