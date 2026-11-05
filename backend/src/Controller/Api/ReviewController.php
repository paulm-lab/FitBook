<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ReviewInput;
use App\Entity\Review;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Http\Pagination;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Security\Voter\ReviewVoter;
use App\Service\CataloguePresenter;
use App\Service\TargetResolver;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Avis notés sur une salle, un coach ou une séance (EF-09, RG-06 à RG-08), et modération
 * par l'administrateur (EF-14).
 */
#[OA\Tag(name: 'Avis')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly CataloguePresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Avis visibles d'une cible : ?type=salle|coach|seance&cibleId=3 (paginé), avec la note moyenne. */
    #[Security(name: null)]
    #[Route('/api/avis', name: 'api_avis_index', methods: ['GET'])]
    public function index(Request $request, TargetResolver $targets): JsonResponse
    {
        $target = $targets->resolve($request->query->get('type'), $request->query->get('cibleId'));

        return $this->json(Pagination::fromRequest($request)->paginate(
            $this->reviews->visibleFor($target),
            fn (Review $r): array => $this->presenter->review($r),
        ) + ['note' => $this->presenter->rating($this->reviews->ratingOf($target))]);
    }

    /** Mes avis, avec leur cible. */
    #[Route('/api/me/avis', name: 'api_me_avis', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        return $this->json(array_map(
            fn (Review $r): array => $this->presenter->review($r, withTarget: true),
            $this->reviews->findBy(['author' => $this->currentUser()], ['createdAt' => 'DESC']),
        ));
    }

    #[OA\RequestBody(content: new OA\JsonContent(example: ['type' => 'coach', 'cibleId' => 4, 'note' => 5, 'commentaire' => 'Très bon cours !']))]
    #[OA\Response(response: 201, description: 'Avis publié')]
    #[OA\Response(response: 403, description: 'Aucune réservation terminée sur la cible')]
    #[OA\Response(response: 409, description: 'Avis déjà donné sur cette cible')]
    #[Route('/api/avis', name: 'api_avis_create', methods: ['POST'])]
    public function create(Request $request, ApiRequest $api, TargetResolver $targets, ReservationRepository $reservations): JsonResponse
    {
        $user = $this->currentUser();
        $data = $api->json($request);
        $target = $targets->resolve($data['type'] ?? null, $data['cibleId'] ?? null);
        $input = $api->validate(ReviewInput::fromArray($data));

        // Les réservations des séances finies passent d'abord à « terminée » (RG-06).
        $reservations->markFinished(new \DateTimeImmutable());
        if (!$reservations->hasFinishedReservationFor($user, $target)) {
            throw ApiException::forbidden('Vous devez avoir participé à une séance pour donner un avis.');
        }
        if ($this->reviews->findOneByAuthorAndTarget($user, $target) !== null) {
            throw ApiException::conflict('Vous avez déjà donné votre avis : modifiez-le.');
        }

        $review = $input->applyTo((new Review())->setAuthor($user)->setTarget($target));
        $this->em->persist($review);
        $this->em->flush();

        return $this->json($this->presenter->review($review, withTarget: true), 201);
    }

    /** Modification par l'auteur : l'avis repasse en attente de modération. */
    #[Route('/api/avis/{id}', name: 'api_avis_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, ApiRequest $api): JsonResponse
    {
        $review = $this->find($id);
        $this->denyAccessUnlessGranted(ReviewVoter::EDIT, $review);

        $api->validate(ReviewInput::fromArray($api->json($request)))->applyTo($review);
        $review->setIsModerated(false);
        $this->em->flush();

        return $this->json($this->presenter->review($review, withTarget: true));
    }

    #[Route('/api/avis/{id}', name: 'api_avis_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $review = $this->find($id);
        $this->denyAccessUnlessGranted(ReviewVoter::DELETE, $review);
        $this->em->remove($review);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** Liste de modération : ?filtre=non_moderes|masques (tous par défaut). */
    #[IsGranted(User::ROLE_ADMIN)]
    #[Route('/api/admin/avis', name: 'api_admin_avis_index', methods: ['GET'])]
    public function moderationList(Request $request): JsonResponse
    {
        return $this->json(Pagination::fromRequest($request)->paginate(
            $this->reviews->forModeration($request->query->get('filtre')),
            fn (Review $r): array => $this->presenter->review($r, withTarget: true),
        ));
    }

    /**
     * Modération (RG-08) : { "action": "valider" | "masquer" | "afficher" }.
     * Un avis masqué n'apparaît plus et ne compte plus dans la note moyenne.
     */
    #[OA\RequestBody(content: new OA\JsonContent(example: ['action' => 'masquer']))]
    #[Route('/api/admin/avis/{id}', name: 'api_admin_avis_moderate', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function moderate(int $id, Request $request, ApiRequest $api): JsonResponse
    {
        $review = $this->find($id);
        $this->denyAccessUnlessGranted(ReviewVoter::MODERATE, $review);

        match ($api->json($request)['action'] ?? null) {
            'valider' => $review->setIsModerated(true)->setIsVisible(true),
            'masquer' => $review->setIsModerated(true)->setIsVisible(false),
            'afficher' => $review->setIsVisible(true),
            default => throw ApiException::validation(['action' => 'Action attendue : valider, masquer ou afficher.']),
        };
        $this->em->flush();

        return $this->json($this->presenter->review($review, withTarget: true));
    }

    private function find(int $id): Review
    {
        return $this->reviews->find($id) ?? throw ApiException::notFound('Avis introuvable.');
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
