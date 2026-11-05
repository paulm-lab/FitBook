<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Http\ApiException;
use App\Repository\NotificationRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Notifications internes de l'utilisateur connecté (bonus) : réservation confirmée,
 * annulée, séance annulée par l'organisateur, rappel.
 */
#[OA\Tag(name: 'Profil')]
#[Route('/api/me/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationRepository $notifications)
    {
    }

    /** Les 30 dernières notifications et le nombre de non lues. */
    #[Route('', name: 'api_me_notifications', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->currentUser();
        $items = $this->notifications->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 30);

        return $this->json([
            'nonLues' => $this->notifications->count(['user' => $user, 'isRead' => false]),
            'items' => array_map(static fn (Notification $n): array => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'message' => $n->getMessage(),
                'lue' => $n->isRead(),
                'date' => $n->getCreatedAt()->format(\DATE_ATOM),
            ], $items),
        ]);
    }

    /** Marque toutes les notifications comme lues. */
    #[Route('/lues', name: 'api_me_notifications_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $this->notifications->createQueryBuilder('n')
            ->update()->set('n.isRead', ':read')->setParameter('read', true)
            ->where('n.user = :user')->setParameter('user', $this->currentUser())
            ->getQuery()->execute();

        return new JsonResponse(null, 204);
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
