<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\UserPresenter;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Réponse de POST /api/login : le jeton JWT et le profil de l'utilisateur connecté.
 *
 * { "token": "…", "id": 1, "nom": "…", "prenom": "…", "email": "…", "telephone": "…", "roles": [...] }
 *
 * Le jeton est aussi posé dans un cookie HttpOnly (voir lexik_jwt_authentication.yaml) :
 * le front actuel, qui s'appuie sur les cookies, continue de fonctionner.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class JwtAuthenticationSuccessListener
{
    public function __construct(private readonly UserPresenter $presenter)
    {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->setData($event->getData() + $this->presenter->present($user));
    }
}
