<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\UserPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Réponse de POST /api/login : le jeton JWT et le profil de l'utilisateur connecté.
 *
 * { "token": "…", "id": 1, "nom": "…", "prenom": "…", "email": "…", "telephone": "…", "roles": [...] }
 *
 * Le jeton est aussi posé dans un cookie HttpOnly (voir lexik_jwt_authentication.yaml) :
 * le front actuel, qui s'appuie sur les cookies, continue de fonctionner.
 *
 * La langue du front (Accept-Language) est mémorisée sur le compte : les e-mails envoyés
 * plus tard (rappels, annulations) partent dans cette langue.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class JwtAuthenticationSuccessListener
{
    public function __construct(
        private readonly UserPresenter $presenter,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->rememberLocale($user);
        $event->setData($event->getData() + $this->presenter->present($user));
    }

    /** Sans en-tête Accept-Language (client API, Postman), la langue du compte est conservée. */
    private function rememberLocale(User $user): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->headers->has('Accept-Language')) {
            return;
        }

        $locale = $user->getLocale();
        if ($user->setLocale($request->getLocale())->getLocale() !== $locale) {
            $this->em->flush();
        }
    }
}
