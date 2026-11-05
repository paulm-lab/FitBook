<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\AvailabilityInput;
use App\Entity\Availability;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Repository\AvailabilityRepository;
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
 * Disponibilités d'un coach ou d'une salle : lecture publique, gestion par le coach lui-même
 * ou le gestionnaire de la salle (US-C03, US-G03).
 */
#[OA\Tag(name: 'Disponibilités')]
#[Route('/api/disponibilites')]
final class AvailabilityController extends AbstractController
{
    public function __construct(
        private readonly AvailabilityRepository $availabilities,
        private readonly CataloguePresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?coachId=… ou ?salleId=… */
    #[Security(name: null)]
    #[Route('', name: 'api_disponibilites_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $criteria = match (true) {
            $request->query->has('coachId') => ['coach' => $request->query->getInt('coachId')],
            $request->query->has('salleId') => ['gym' => $request->query->getInt('salleId')],
            default => throw ApiException::badRequest('Précisez coachId ou salleId.'),
        };

        return $this->json(array_map(
            fn (Availability $a): array => $this->presenter->availability($a),
            $this->availabilities->findBy($criteria, ['isRecurring' => 'DESC', 'dayOfWeek' => 'ASC', 'date' => 'ASC', 'startTime' => 'ASC']),
        ));
    }

    #[OA\RequestBody(content: new OA\JsonContent(example: ['recurrent' => true, 'jourSemaine' => 1, 'heureDebut' => '09:00', 'heureFin' => '12:00']))]
    #[Route('', name: 'api_disponibilites_create', methods: ['POST'])]
    public function create(Request $request, ApiRequest $api): JsonResponse
    {
        $input = $api->validate(AvailabilityInput::fromArray($api->json($request)));
        $availability = $input->applyTo(new Availability());

        if ($input->salleId !== null) {
            $gym = $this->em->find(Gym::class, $input->salleId) ?? throw ApiException::validation(['salleId' => 'Cette salle n’existe pas.']);
            $this->denyAccessUnlessGranted(GymVoter::EDIT, $gym);
            $availability->setGym($gym);
        } else {
            $availability->setCoach($this->ownCoachProfile());
        }

        $this->em->persist($availability);
        $this->em->flush();

        return $this->json($this->presenter->availability($availability), 201);
    }

    #[Route('/{id}', name: 'api_disponibilites_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, ApiRequest $api): JsonResponse
    {
        $availability = $this->findOwned($id);
        $api->validate(AvailabilityInput::fromArray($api->json($request)))->applyTo($availability);
        $this->em->flush();

        return $this->json($this->presenter->availability($availability));
    }

    #[Route('/{id}', name: 'api_disponibilites_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->em->remove($this->findOwned($id));
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** Créneau modifiable par son coach, le gestionnaire de sa salle ou l'administrateur. */
    private function findOwned(int $id): Availability
    {
        $availability = $this->availabilities->find($id) ?? throw ApiException::notFound('Créneau introuvable.');
        $user = $this->getUser();
        $allowed = $this->isGranted(User::ROLE_ADMIN)
            || ($availability->getCoach() !== null && $availability->getCoach()->getUser() === $user)
            || ($availability->getGym() !== null && $this->isGranted(GymVoter::EDIT, $availability->getGym()));
        if (!$allowed) {
            throw ApiException::forbidden('Ce créneau ne vous appartient pas.');
        }

        return $availability;
    }

    private function ownCoachProfile(): CoachProfile
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->hasRole(User::ROLE_COACH)) {
            throw ApiException::forbidden('Seuls les coachs déclarent leurs disponibilités (salleId pour une salle).');
        }
        if ($user->getCoachProfile() === null) {
            $user->setCoachProfile(new CoachProfile());
            $this->em->persist($user->getCoachProfile());
        }

        return $user->getCoachProfile();
    }
}
