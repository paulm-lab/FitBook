<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use App\Service\FitBookMailer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    #[Route('', name: 'api_reservations_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ReservationRepository $reservations, FitBookMailer $mailer, LoggerInterface $logger): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Authentification requise.'], 401);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps doit être un JSON valide.'], 400);
        }

        $seanceId = filter_var($data['seanceId'] ?? null, FILTER_VALIDATE_INT);
        $nombrePlaces = filter_var($data['nombrePlaces'] ?? 1, FILTER_VALIDATE_INT);
        if (!$seanceId || !$nombrePlaces || $nombrePlaces < 1 || $nombrePlaces > 20) {
            return $this->json(['message' => 'La séance ou le nombre de places est invalide.'], 400);
        }

        $entityManager->beginTransaction();
        try {
            $seance = $entityManager->find(Seance::class, $seanceId, LockMode::PESSIMISTIC_WRITE);
            if (!$seance instanceof Seance) {
                $entityManager->rollback();
                return $this->json(['message' => 'Séance introuvable.'], 404);
            }
            if ($seance->getStatut() !== 'actif' || $seance->getDateDebut() <= new \DateTimeImmutable()) {
                $entityManager->rollback();
                return $this->json(['message' => 'Cette séance n’est plus disponible.'], 409);
            }

            if ($reservations->findOneBy(['utilisateur' => $user, 'seance' => $seance, 'statut' => 'confirmee'])) {
                $entityManager->rollback();
                return $this->json(['message' => 'Vous avez déjà réservé cette séance.'], 409);
            }
            if ($seance->getPlacesDisponibles() < $nombrePlaces) {
                $entityManager->rollback();
                return $this->json(['message' => 'Il ne reste pas assez de places.'], 409);
            }

            $total = number_format((float) $seance->getPrix() * $nombrePlaces, 2, '.', '');
            $reservation = (new Reservation())
                ->setUtilisateur($user)
                ->setSeance($seance)
                ->setDateReservation(new \DateTimeImmutable('today'))
                ->setStatut('confirmee')
                ->setNombrePlaces($nombrePlaces)
                ->setMontantTotal($total);
            $seance->setPlacesDisponibles($seance->getPlacesDisponibles() - $nombrePlaces);
            $entityManager->persist($reservation);
            $entityManager->flush();
            $entityManager->commit();
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
                'statut' => $reservation->getStatut(),
                'nombrePlaces' => $reservation->getNombrePlaces(),
                'montantTotal' => $reservation->getMontantTotal(),
            ], 201);
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }
            throw $exception;
        }
    }
}
