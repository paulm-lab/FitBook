<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Session;
use App\Enum\SessionStatus;
use App\Repository\SessionRepository;
use App\Service\CataloguePresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Séances publiques : liste filtrable des séances à venir et détail d'une séance.
 * (La recherche avancée paginée /api/search arrive en Phase 4.)
 */
#[Route('/api/seances')]
final class SeanceController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    #[Route('', name: 'api_seances_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $day = null;
        $date = $request->query->get('date');
        if ($date !== null && $date !== '') {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
            if (!$day || $day->format('Y-m-d') !== $date) {
                return $this->json(['message' => 'Le filtre date doit être au format YYYY-MM-DD.'], 400);
            }
        }

        $items = $this->sessions->findUpcoming(
            new \DateTimeImmutable(),
            trim((string) $request->query->get('activite', '')),
            trim((string) $request->query->get('lieu', '')),
            $day,
        );
        $counts = $this->sessions->confirmedCounts($items);

        return $this->json(array_map(
            fn (Session $s): array => $this->presenter->session($s, $counts[$s->getId()] ?? 0),
            $items,
        ));
    }

    /** Détail d'une séance active et à venir : comme la liste, les séances passées sont masquées. */
    #[Route('/{id}', name: 'api_seances_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->sessions->find($id);
        if (!$item || $item->getStatus() !== SessionStatus::Active || $item->getStartAt() <= new \DateTimeImmutable()) {
            return $this->json(['message' => 'Séance introuvable.'], 404);
        }

        $counts = $this->sessions->confirmedCounts([$item]);

        return $this->json($this->presenter->session($item, $counts[$item->getId()] ?? 0));
    }
}
