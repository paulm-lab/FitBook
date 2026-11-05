<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ActivityInput;
use App\Entity\Activity;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Repository\ActivityRepository;
use App\Service\CataloguePresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Activités (disciplines) : lecture publique, gestion par l'administrateur (US-A03).
 */
#[OA\Tag(name: 'Activités')]
#[Route('/api/activites')]
final class ActiviteController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly CataloguePresenter $presenter,
        private readonly ApiRequest $api,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Security(name: null)]
    #[Route('', name: 'api_activites_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(array_map(
            fn (Activity $item): array => $this->presenter->activity($item),
            $this->activities->findBy([], ['name' => 'ASC']),
        ));
    }

    #[Security(name: null)]
    #[Route('/{id}', name: 'api_activites_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->presenter->activity($this->find($id)));
    }

    #[OA\RequestBody(content: new OA\JsonContent(example: ['nom' => 'Aquagym', 'categorie' => 'Bien-être', 'description' => 'Renforcement dans l’eau.', 'image' => 'https://exemple.test/aquagym.jpg']))]
    #[Route('', name: 'api_activites_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        $input = $this->api->validate(ActivityInput::fromArray($this->api->json($request)));
        $this->assertUniqueName($input->nom, null);

        $activity = $input->applyTo(new Activity());
        $this->em->persist($activity);
        $this->em->flush();

        return $this->json($this->presenter->activity($activity), 201);
    }

    #[Route('/{id}', name: 'api_activites_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        $activity = $this->find($id);
        $input = $this->api->validate(ActivityInput::fromArray($this->api->json($request)));
        $this->assertUniqueName($input->nom, $activity);

        $input->applyTo($activity);
        $this->em->flush();

        return $this->json($this->presenter->activity($activity));
    }

    /** Une activité qui a des séances ne peut pas être supprimée (RESTRICT en base). */
    #[Route('/{id}', name: 'api_activites_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        $activity = $this->find($id);
        if ($activity->getSessions()->count() > 0) {
            throw ApiException::conflict('Des séances utilisent cette activité : elle ne peut pas être supprimée.');
        }

        $this->em->remove($activity);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function assertUniqueName(string $name, ?Activity $current): void
    {
        $existing = $this->activities->createQueryBuilder('a')
            ->andWhere('LOWER(a.name) = :name')->setParameter('name', mb_strtolower($name))
            ->getQuery()->getOneOrNullResult();
        if ($existing !== null && $existing !== $current) {
            throw ApiException::conflict('Une activité porte déjà ce nom.');
        }
    }

    private function find(int $id): Activity
    {
        return $this->activities->find($id) ?? throw ApiException::notFound('Activité introuvable.');
    }
}
