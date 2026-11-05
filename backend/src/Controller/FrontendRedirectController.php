<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Les pages (/inscription, /connexion…) appartiennent au front Angular ; Symfony ne sert que l'API /api.
 * Une page ouverte par erreur sur le backend est renvoyée vers la même page du front au lieu d'un 404.
 */
final class FrontendRedirectController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(resolve:FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    // Priorité basse : toute route existante (API, profiler, /home) reste prioritaire.
    #[Route(
        '/{path}',
        name: 'app_frontend_redirect',
        requirements: ['path' => '(?!api(/|$)|_(profiler|wdt|error)(/|$)|bundles/).*'],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: -100,
    )]
    public function redirectToFrontend(Request $request, string $path): RedirectResponse
    {
        $query = $request->getQueryString();

        return $this->redirect(rtrim($this->frontendUrl, '/') . '/' . $path . ($query !== null ? '?' . $query : ''));
    }
}
