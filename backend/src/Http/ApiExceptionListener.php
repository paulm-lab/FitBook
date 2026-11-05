<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sous /api, toute erreur est renvoyée en JSON (jamais de page HTML au front) :
 * - ApiException : code et message métier, avec le détail des champs en 422 ;
 * - exceptions HTTP de Symfony (404 entité introuvable, 405 méthode…) : leur code ;
 * - les autres erreurs restent gérées par Symfony (page de débogage en dev, 500 en prod).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class ApiExceptionListener
{
    private const MESSAGES = [
        400 => 'Requête invalide.',
        404 => 'Ressource introuvable.',
        405 => 'Méthode non autorisée.',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ApiException) {
            $body = ['message' => $exception->getMessage()];
            if ($exception->getErrors() !== []) {
                $body['errors'] = $exception->getErrors();
            }
            $event->setResponse(new JsonResponse($body, $exception->getStatusCode()));

            return;
        }

        // Les refus d'accès sont traités par le pare-feu (ApiAuthenticationEntryPoint).
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() !== 401 && $exception->getStatusCode() !== 403) {
            $status = $exception->getStatusCode();
            $event->setResponse(new JsonResponse(
                ['message' => self::MESSAGES[$status] ?? 'La requête n’a pas pu aboutir.'],
                $status,
                $exception->getHeaders(),
            ));
        }
    }
}
