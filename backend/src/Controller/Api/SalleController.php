<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\GymInput;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Http\Pagination;
use App\Repository\AvailabilityRepository;
use App\Repository\GymRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GymVoter;
use App\Service\CataloguePresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Salles : liste et fiche publiques ; création par un gestionnaire (ou l'admin), modification,
 * suppression, statistiques et réservations par son gestionnaire (GymVoter, RG-11).
 */
#[OA\Tag(name: 'Salles')]
#[Route('/api/salles')]
final class SalleController extends AbstractController
{
    public function __construct(
        private readonly GymRepository $gyms,
        private readonly ReviewRepository $reviews,
        private readonly CataloguePresenter $presenter,
        private readonly ApiRequest $api,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Security(name: null)]
    #[Route('', name: 'api_salles_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->gyms->findBy([], ['name' => 'ASC']);
        $ratings = $this->reviews->ratings(Gym::class, array_map(static fn (Gym $g): ?int => $g->getId(), $items));

        return $this->json(array_map(
            fn (Gym $gym): array => $this->presenter->gym($gym) + ['noteMoyenne' => $ratings[$gym->getId()]['note'] ?? null, 'nbAvis' => $ratings[$gym->getId()]['nbAvis'] ?? 0],
            $items,
        ));
    }

    /** Fiche : informations, prochaines séances, disponibilités, note moyenne et avis visibles. */
    #[Security(name: null)]
    #[Route('/{id}', name: 'api_salles_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, SessionRepository $sessions, AvailabilityRepository $availabilities): JsonResponse
    {
        $gym = $this->find($id);
        $upcoming = $sessions->upcomingFor($gym, new \DateTimeImmutable());
        $counts = $sessions->confirmedCounts($upcoming);

        return $this->json($this->presenter->gym($gym) + [
            'note' => $this->presenter->rating($this->reviews->ratingOf($gym)),
            'avis' => array_map(fn ($r): array => $this->presenter->review($r), $this->reviews->visibleFor($gym)->setMaxResults(30)->getQuery()->getResult()),
            'prochainesSeances' => array_map(fn (Session $s): array => $this->presenter->session($s, $counts[$s->getId()] ?? 0), $upcoming),
            'disponibilites' => array_map(fn ($a): array => $this->presenter->availability($a), $availabilities->findBy(['gym' => $gym])),
            'gestionnaireId' => $gym->getManager()?->getId(),
            'peutModifier' => $this->isGranted(GymVoter::EDIT, $gym),
        ]);
    }

    #[OA\RequestBody(content: new OA\JsonContent(example: ['nom' => 'FitBook Louise', 'adresse' => 'Avenue Louise 100', 'ville' => 'Bruxelles', 'codePostal' => '1050', 'latitude' => 50.83, 'longitude' => 4.36, 'horaires' => ['lundi' => '07:00-22:00'], 'photos' => []]))]
    #[OA\Response(response: 201, description: 'Salle créée')]
    #[Route('', name: 'api_salles_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(User::ROLE_GESTIONNAIRE);
        $input = $this->api->validate(GymInput::fromArray($this->api->json($request)));

        $gym = $input->applyTo(new Gym());
        $gym->setManager($this->managerFor($input));
        $this->em->persist($gym);
        $this->em->flush();

        return $this->json($this->presenter->gym($gym), 201);
    }

    #[Route('/{id}', name: 'api_salles_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $gym = $this->find($id);
        $this->denyAccessUnlessGranted(GymVoter::EDIT, $gym);
        $input = $this->api->validate(GymInput::fromArray($this->api->json($request)));

        $input->applyTo($gym);
        // Seul l'administrateur peut confier la salle à un autre gestionnaire.
        if ($input->gestionnaireId !== null && $this->isGranted(User::ROLE_ADMIN)) {
            $gym->setManager($this->managerFor($input));
        }
        $this->em->flush();

        return $this->json($this->presenter->gym($gym));
    }

    /** Suppression refusée tant que des séances y sont rattachées (RESTRICT en base, MLD § 3). */
    #[Route('/{id}', name: 'api_salles_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $gym = $this->find($id);
        $this->denyAccessUnlessGranted(GymVoter::DELETE, $gym);
        if ($gym->getSessions()->count() > 0) {
            throw ApiException::conflict('Cette salle a des séances : supprimez-les ou annulez-les d’abord.');
        }

        $this->em->remove($gym);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** Tableau de bord d'une salle (US-G05) : chiffres de sp_stats_salle + évolution hebdomadaire. */
    #[Route('/{id}/stats', name: 'api_salles_stats', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stats(int $id, ReservationRepository $reservations): JsonResponse
    {
        $gym = $this->find($id);
        $this->denyAccessUnlessGranted(GymVoter::VIEW_STATS, $gym);

        return $this->json($this->gyms->stats($gym) + [
            'salle' => ['id' => $gym->getId(), 'nom' => $gym->getName()],
            'evolution' => $reservations->weeklyCounts($gym, new \DateTimeImmutable()),
        ]);
    }

    /** Réservations des séances de la salle (US-G04), paginées, des plus proches aux plus lointaines. */
    #[Route('/{id}/reservations', name: 'api_salles_reservations', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function reservations(int $id, Request $request, ReservationRepository $reservations): JsonResponse
    {
        $gym = $this->find($id);
        $this->denyAccessUnlessGranted(GymVoter::VIEW_STATS, $gym);
        $now = new \DateTimeImmutable();

        $query = $reservations->forOrganizer(gym: $gym, upcomingOnly: $request->query->get('periode', 'a_venir') === 'a_venir', now: $now);

        return $this->json(Pagination::fromRequest($request)->paginate(
            $query,
            fn (Reservation $r): array => $this->presenter->reservation($r, $now, withUser: true),
        ));
    }

    private function managerFor(GymInput $input): User
    {
        $user = $this->getUser();
        if ($input->gestionnaireId === null || !$this->isGranted(User::ROLE_ADMIN)) {
            if (!$user instanceof User || !$user->hasRole(User::ROLE_GESTIONNAIRE)) {
                throw ApiException::validation(['gestionnaireId' => 'Choisissez le gestionnaire de la salle.']);
            }

            return $user;
        }

        $manager = $this->em->find(User::class, $input->gestionnaireId);
        if (!$manager instanceof User || !$manager->hasRole(User::ROLE_GESTIONNAIRE)) {
            throw ApiException::validation(['gestionnaireId' => 'Ce compte n’est pas un gestionnaire.']);
        }

        return $manager;
    }

    private function find(int $id): Gym
    {
        return $this->gyms->find($id) ?? throw ApiException::notFound('Salle introuvable.');
    }
}
