<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use App\Service\FitBookMailer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création d'une réservation : 1 réservation = 1 place (décision D3).
 *
 * La séance est verrouillée (SELECT … FOR UPDATE) pendant le contrôle de capacité : deux
 * demandes simultanées sur la dernière place ne peuvent pas aboutir toutes les deux (RG-03).
 * En Phase 4, ce contrôle passera par la procédure stockée sp_reserver_seance.
 */
#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'api_reservations_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationRepository $reservations,
        SessionRepository $sessions,
        FitBookMailer $mailer,
        LoggerInterface $logger,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps doit être un JSON valide.'], 400);
        }

        $sessionId = filter_var($data['seanceId'] ?? null, FILTER_VALIDATE_INT);
        $places = filter_var($data['nombrePlaces'] ?? 1, FILTER_VALIDATE_INT);
        if (!$sessionId) {
            return $this->json(['message' => 'La séance est invalide.'], 400);
        }
        if ($places !== 1) {
            return $this->json(['message' => 'Une réservation correspond à une seule place.'], 400);
        }

        $entityManager->beginTransaction();
        try {
            $session = $entityManager->find(Session::class, $sessionId, LockMode::PESSIMISTIC_WRITE);
            if (!$session instanceof Session) {
                $entityManager->rollback();

                return $this->json(['message' => 'Séance introuvable.'], 404);
            }
            if ($session->getStatus() !== SessionStatus::Active || $session->getStartAt() <= new \DateTimeImmutable()) {
                $entityManager->rollback();

                return $this->json(['message' => 'Cette séance n’est plus disponible.'], 409);
            }

            // Une ligne par couple (utilisateur, séance) : on la réactive si elle était annulée.
            $reservation = $reservations->findOneBy(['user' => $user, 'session' => $session]);
            if ($reservation?->getStatus() === ReservationStatus::Confirmee) {
                $entityManager->rollback();

                return $this->json(['message' => 'Vous avez déjà réservé cette séance.'], 409);
            }

            $taken = $sessions->confirmedCounts([$session])[$session->getId()] ?? 0;
            if ($taken >= $session->getCapacity()) {
                $entityManager->rollback();

                return $this->json(['message' => 'Il ne reste plus de place.'], 409);
            }

            $reservation ??= (new Reservation())->setUser($user)->setSession($session);
            $reservation
                ->setStatus(ReservationStatus::Confirmee)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setCancelledAt(null);
            $entityManager->persist($reservation);
            $entityManager->flush();
            $entityManager->commit();
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }
            throw $exception;
        }

        // La réservation est déjà validée : un échec d'envoi ne doit pas la faire échouer.
        try {
            $mailer->sendReservationConfirmation($reservation);
        } catch (\Throwable $exception) {
            $logger->warning('Réservation enregistrée, mais échec de l’e-mail de confirmation.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $this->json([
            'id' => $reservation->getId(),
            'statut' => $reservation->getStatus()->value,
            'nombrePlaces' => 1,
            'montantTotal' => $session->getPrice(),
        ], 201);
    }
}
