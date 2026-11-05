<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réservation d'une place par un sportif (1 réservation = 1 place, décision D3).
 *
 * Le couple (utilisateur, séance) est unique (RG-04) : une réservation annulée puis refaite
 * réactive la même ligne.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_reservation_user_session', columns: ['user_id', 'session_id'])]
#[ORM\Index(name: 'idx_reservation_session_status', columns: ['session_id', 'status'])]
class Reservation
{
    /** Délai minimal avant le début de la séance pour pouvoir annuler (RG-05). */
    public const CANCELLATION_DELAY = '+2 hours';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    #[ORM\Column(length: 20, enumType: ReservationStatus::class)]
    private ReservationStatus $status = ReservationStatus::Confirmee;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /** Jeton du QR code présenté à l'accueil (bonus). */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $qrToken = null;

    /** Empêche l'envoi de deux rappels pour la même réservation. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getQrToken(): ?string
    {
        return $this->qrToken;
    }

    public function setQrToken(?string $qrToken): static
    {
        $this->qrToken = $qrToken;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }

    /** Annulable si confirmée et si la séance commence dans plus de 2 heures (RG-05). */
    public function isCancellable(\DateTimeImmutable $now): bool
    {
        $startAt = $this->session?->getStartAt();

        return $this->status === ReservationStatus::Confirmee
            && $startAt !== null
            && $startAt > $now->modify(self::CANCELLATION_DELAY);
    }
}
