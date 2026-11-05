<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\Pagination;
use App\Repository\GymRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;
use App\Service\CataloguePresenter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace du gestionnaire connecté (/api/gestionnaire/…) : ses salles avec leurs statistiques,
 * les séances et les réservations de ses salles, les avis reçus (US-G01 à US-G06).
 */
#[OA\Tag(name: 'Espace gestionnaire')]
#[IsGranted(User::ROLE_GESTIONNAIRE)]
#[Route('/api/gestionnaire')]
final class ManagerController extends AbstractController
{
    public function __construct(
        private readonly GymRepository $gyms,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    /** Mes salles, chacune avec les chiffres de sp_stats_salle. */
    #[Route('/salles', name: 'api_manager_gyms', methods: ['GET'])]
    public function gyms(): JsonResponse
    {
        return $this->json(array_map(
            fn (Gym $gym): array => $this->presenter->gym($gym) + ['stats' => $this->gyms->stats($gym)],
            $this->gyms->findBy(['manager' => $this->currentUser()], ['name' => 'ASC']),
        ));
    }

    /** Tableau de bord global : totaux de toutes mes salles et évolution hebdomadaire. */
    #[Route('/stats', name: 'api_manager_stats', methods: ['GET'])]
    public function stats(ReservationRepository $reservations): JsonResponse
    {
        $user = $this->currentUser();
        $stats = array_map(fn (Gym $gym): array => ['salle' => ['id' => $gym->getId(), 'nom' => $gym->getName()]] + $this->gyms->stats($gym), $this->gyms->findBy(['manager' => $user], ['name' => 'ASC']));
        $capacity = array_sum(array_column($stats, 'capaciteTotale'));
        $booked = array_sum(array_column($stats, 'nbReservations'));

        return $this->json([
            'nbSalles' => \count($stats),
            'nbSeances' => array_sum(array_column($stats, 'nbSeances')),
            'nbReservations' => $booked,
            'tauxRemplissage' => $capacity > 0 ? round(100 * $booked / $capacity, 1) : null,
            'parSalle' => $stats,
            'evolution' => $reservations->weeklyCounts(null, new \DateTimeImmutable(), manager: $user),
        ]);
    }

    /** Séances de mes salles : ?periode=a_venir|passees, ou ?debut=&fin= pour le calendrier. */
    #[Route('/seances', name: 'api_manager_sessions', methods: ['GET'])]
    public function sessions(Request $request, SessionRepository $sessions): JsonResponse
    {
        $query = $sessions->managedBy(null, null, new \DateTimeImmutable())
            ->andWhere('g.manager = :manager')->setParameter('manager', $this->currentUser());
        if ($request->query->getInt('salleId') > 0) {
            $query->andWhere('g.id = :gymId')->setParameter('gymId', $request->query->getInt('salleId'));
        }

        return $this->json(CoachController::sessionList($request, $sessions, $this->presenter, $query, $request->query->get('periode')));
    }

    /** Réservations des séances de mes salles (US-G04), ?periode=a_venir (défaut) ou toutes. */
    #[Route('/reservations', name: 'api_manager_reservations', methods: ['GET'])]
    public function reservations(Request $request, ReservationRepository $reservations): JsonResponse
    {
        $now = new \DateTimeImmutable();

        return $this->json(Pagination::fromRequest($request)->paginate(
            $reservations->forOrganizer(manager: $this->currentUser(), upcomingOnly: $request->query->get('periode', 'a_venir') === 'a_venir', now: $now, status: $request->query->get('statut')),
            fn (Reservation $r): array => $this->presenter->reservation($r, $now, withUser: true),
        ));
    }

    /** Avis visibles laissés sur mes salles et leurs séances (US-G06). */
    #[Route('/avis', name: 'api_manager_reviews', methods: ['GET'])]
    public function reviews(Request $request, ReviewRepository $reviews): JsonResponse
    {
        return $this->json(Pagination::fromRequest($request)->paginate(
            $reviews->receivedBy($this->currentUser(), null),
            fn ($review): array => $this->presenter->review($review, withTarget: true),
        ));
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'Authentification requise.');
        }

        return $user;
    }
}
