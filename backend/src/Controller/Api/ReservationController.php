<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Http\JsonSchemaValidator;
use App\Http\Pagination;
use App\Repository\ReservationRepository;
use App\Security\Voter\ReservationVoter;
use App\Service\CataloguePresenter;
use App\Service\ReservationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Réservations du sportif connecté : réserver (1 réservation = 1 place, décision D3),
 * consulter, annuler jusqu'à 2 h avant la séance (RG-03 à RG-05).
 *
 * Le corps de POST est validé par le JSON Schema config/schemas/reservation.schema.json ;
 * le contrôle de capacité est fait par la procédure stockée sp_reserver_seance (MySQL).
 */
#[OA\Tag(name: 'Réservations')]
#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['seanceId'], properties: [
        new OA\Property(property: 'seanceId', type: 'integer', minimum: 1, example: 12),
    ]))]
    #[OA\Response(response: 201, description: 'Réservation confirmée')]
    #[OA\Response(response: 404, description: 'Séance introuvable')]
    #[OA\Response(response: 409, description: 'Complet, déjà réservée, séance passée ou annulée')]
    #[OA\Response(response: 422, description: 'Corps non conforme au JSON Schema')]
    #[Route('', name: 'api_reservations_create', methods: ['POST'])]
    public function create(Request $request, ApiRequest $api, JsonSchemaValidator $schema): JsonResponse
    {
        $data = $api->json($request);
        $schema->validate($data, 'reservation');

        $reservation = $this->service->reserve($this->currentUser(), (int) $data['seanceId']);
        $session = $reservation->getSession();

        return $this->json($this->presenter->reservation($reservation, new \DateTimeImmutable()) + [
            'nombrePlaces' => 1,
            'montantTotal' => $session?->getPrice(),
        ], 201);
    }

    /** Mes réservations : ?periode=a_venir (défaut) ou historique (terminées, annulées, passées). */
    #[OA\Parameter(name: 'periode', in: 'query', schema: new OA\Schema(type: 'string', enum: ['a_venir', 'historique']))]
    #[Route('', name: 'api_reservations_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservations): JsonResponse
    {
        $period = $request->query->get('periode', 'a_venir') === 'historique' ? 'historique' : 'a_venir';
        $now = new \DateTimeImmutable();

        return $this->json(Pagination::fromRequest($request)->paginate(
            $reservations->forUser($this->currentUser(), $period, $now),
            fn (Reservation $r): array => $this->presenter->reservation($r, $now),
        ));
    }

    #[Route('/{id}', name: 'api_reservations_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ReservationRepository $reservations): JsonResponse
    {
        $reservation = $reservations->find($id) ?? throw ApiException::notFound('Réservation introuvable.');
        $this->denyAccessUnlessGranted(ReservationVoter::VIEW, $reservation);

        return $this->json($this->presenter->reservation($reservation, new \DateTimeImmutable()));
    }

    /** Annulation par le sportif (sp_annuler_reservation) : 409 à moins de 2 h du début. */
    #[OA\Response(response: 200, description: 'Réservation annulée')]
    #[OA\Response(response: 403, description: 'Réservation d’un autre sportif')]
    #[OA\Response(response: 409, description: 'Trop tard ou déjà annulée')]
    #[Route('/{id}/annuler', name: 'api_reservations_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, ReservationRepository $reservations): JsonResponse
    {
        $reservation = $reservations->find($id) ?? throw ApiException::notFound('Réservation introuvable.');
        $user = $this->currentUser();

        // L'administrateur annule sans condition de délai (correction des données, US-A03).
        if ($this->isGranted(User::ROLE_ADMIN) && $reservation->getUser() !== $user) {
            $this->service->cancelByAdmin($reservation);
        } else {
            $this->service->cancel($user, $reservation);
        }

        return $this->json($this->presenter->reservation($reservation, new \DateTimeImmutable()));
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
