<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatus;
use App\Enum\SessionLevel;
use App\Enum\SessionStatus;
use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séance (cours) : rattachée à une activité, et à une salle et/ou un coach (RG-10).
 *
 * Les contraintes `fin > début`, `capacité ≥ 1`, `prix ≥ 0` et « salle ou coach » sont
 * aussi garanties en base par des CHECK (voir la migration initiale).
 */
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: '`session`')]
#[ORM\Index(name: 'idx_session_status_start', columns: ['status', 'start_at'])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Activity $activity = null;

    /** RESTRICT : on annule les séances avant de supprimer la salle (voir MLD § 3). */
    #[ORM\ManyToOne(targetEntity: Gym::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Gym $gym = null;

    #[ORM\ManyToOne(targetEntity: CoachProfile::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?CoachProfile $coach = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $capacity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, options: ['default' => '0.00'])]
    private string $price = '0.00';

    #[ORM\Column(length: 20, enumType: SessionLevel::class)]
    private SessionLevel $level = SessionLevel::Debutant;

    #[ORM\Column(length: 20, enumType: SessionStatus::class, options: ['default' => 'active'])]
    private SessionStatus $status = SessionStatus::Active;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'session')]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): static
    {
        $this->activity = $activity;

        return $this;
    }

    public function getGym(): ?Gym
    {
        return $this->gym;
    }

    public function setGym(?Gym $gym): static
    {
        $this->gym = $gym;

        return $this;
    }

    public function getCoach(): ?CoachProfile
    {
        return $this->coach;
    }

    public function setCoach(?CoachProfile $coach): static
    {
        $this->coach = $coach;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getLevel(): SessionLevel
    {
        return $this->level;
    }

    public function setLevel(SessionLevel $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }

    public function setStatus(SessionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    /** Nombre de réservations confirmées (les places prises). */
    public function getConfirmedCount(): int
    {
        return $this->reservations
            ->filter(static fn (Reservation $r): bool => $r->getStatus() === ReservationStatus::Confirmee)
            ->count();
    }

    /** Places restantes, calculées : la capacité n'est jamais décrémentée en base. */
    public function getRemainingPlaces(): int
    {
        return max(0, $this->capacity - $this->getConfirmedCount());
    }

    /** Une séance est réservable si elle est active, pas encore commencée et pas complète. */
    public function isBookable(\DateTimeImmutable $now): bool
    {
        return $this->status === SessionStatus::Active
            && $this->startAt !== null
            && $this->startAt > $now
            && $this->getRemainingPlaces() > 0
            && $this->hasAvailableCoach();
    }

    /**
     * Le coach éventuel peut animer la séance : compte vérifié et non bloqué. La séance d'un
     * coach bloqué disparaît du catalogue et n'est plus réservable.
     */
    public function hasAvailableCoach(): bool
    {
        $user = $this->coach?->getUser();

        return $user === null || ($user->isVerified() && !$user->isBlocked());
    }
}
