<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Jeton refusé (invalide, expiré, compte bloqué) : la réponse 401 efface aussi le cookie BEARER.
 *
 * Sans cela, le navigateur renverrait le cookie rejeté à chaque requête, et même les routes
 * publiques répondraient 401 jusqu'à son expiration.
 */
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class JwtFailureCookieListener
{
    public function __invoke(JWTFailureEventInterface $event): void
    {
        $event->getResponse()?->headers->clearCookie('BEARER', '/', null, false, true, Cookie::SAMESITE_LAX);
    }
}
