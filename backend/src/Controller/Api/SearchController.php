<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SearchCriteria;
use App\Http\ApiRequest;
use App\Http\Pagination;
use App\Service\SearchService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche avancée paginée de séances, salles et coachs (EF-01).
 *
 * Exemple : /api/recherche?type=seances&q=yoga&ville=Ixelles&du=2026-11-10&prixMax=15&tri=prix&page=2
 */
#[OA\Tag(name: 'Recherche')]
final class SearchController extends AbstractController
{
    #[Security(name: null)]
    #[OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: SearchCriteria::TYPES))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Texte libre', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'ville', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'activite', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'du', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'au', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'prixMax', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'string', enum: ['debutant', 'intermediaire', 'avance']))]
    #[OA\Parameter(name: 'noteMin', in: 'query', schema: new OA\Schema(type: 'number', minimum: 1, maximum: 5))]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'rayon', in: 'query', description: 'Rayon en km', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'tri', in: 'query', schema: new OA\Schema(type: 'string', enum: SearchCriteria::SORTS))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'parPage', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 50))]
    #[OA\Response(response: 200, description: '{ items, page, parPage, total, pages }')]
    #[OA\Response(response: 422, description: 'Critère invalide')]
    #[Route('/api/recherche', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, ApiRequest $api, SearchService $search): JsonResponse
    {
        $criteria = $api->validate(SearchCriteria::fromRequest($request));

        return $this->json($search->search($criteria, Pagination::fromRequest($request, 12), new \DateTimeImmutable()));
    }
}
