<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Favorite;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Repository\FavoriteRepository;
use App\Service\CataloguePresenter;
use App\Service\TargetResolver;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Favoris de l'utilisateur connecté : salles, coachs et séances (EF-08, US-S05).
 */
#[OA\Tag(name: 'Favoris')]
#[Route('/api/favoris')]
final class FavoriteController extends AbstractController
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    /** Mes favoris, du plus récent au plus ancien ; le front les regroupe par type. */
    #[Route('', name: 'api_favoris_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(array_map(
            fn (Favorite $f): array => $this->present($f),
            $this->favorites->findBy(['user' => $this->currentUser()], ['createdAt' => 'DESC']),
        ));
    }

    #[OA\RequestBody(content: new OA\JsonContent(example: ['type' => 'salle', 'cibleId' => 3]))]
    #[OA\Response(response: 201, description: 'Ajouté')]
    #[OA\Response(response: 409, description: 'Déjà en favori')]
    #[Route('', name: 'api_favoris_create', methods: ['POST'])]
    public function create(Request $request, ApiRequest $api, TargetResolver $targets, EntityManagerInterface $em): JsonResponse
    {
        $data = $api->json($request);
        $target = $targets->resolve($data['type'] ?? null, $data['cibleId'] ?? null);
        $user = $this->currentUser();

        $field = array_search($target::class, TargetResolver::TYPES, true);
        $existing = $this->favorites->findOneBy(['user' => $user, ['salle' => 'gym', 'coach' => 'coach', 'seance' => 'session'][$field] => $target]);
        if ($existing !== null) {
            throw ApiException::conflict('Déjà dans vos favoris.');
        }

        $favorite = (new Favorite())->setUser($user)->setTarget($target);
        $em->persist($favorite);
        $em->flush();

        return $this->json($this->present($favorite), 201);
    }

    #[Route('/{id}', name: 'api_favoris_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $favorite = $this->favorites->find($id);
        // Le favori d'un autre est traité comme inexistant : aucune information n'est divulguée.
        if ($favorite === null || $favorite->getUser() !== $this->currentUser()) {
            throw ApiException::notFound('Favori introuvable.');
        }

        $em->remove($favorite);
        $em->flush();

        return new JsonResponse(null, 204);
    }

    /** @return array<string, mixed> */
    private function present(Favorite $favorite): array
    {
        return [
            'id' => $favorite->getId(),
            'type' => $this->presenter->target($favorite->getTarget())['type'] ?? null,
            'date' => $favorite->getCreatedAt()->format(\DATE_ATOM),
            'cible' => $this->presenter->target($favorite->getTarget()),
        ];
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
