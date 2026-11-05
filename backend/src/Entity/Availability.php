<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Créneau disponible d'un coach **ou** d'une salle (exactement un propriétaire, CHECK en base).
 *
 * Récurrent : `dayOfWeek` (1 = lundi … 7 = dimanche). Ponctuel : `date`.
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CoachProfile::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CoachProfile $coach = null;

    #[ORM\ManyToOne(targetEntity: Gym::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Gym $gym = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column]
    private bool $isRecurring = true;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $date = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getGym(): ?Gym
    {
        return $this->gym;
    }

    public function setGym(?Gym $gym): static
    {
        $this->gym = $gym;

        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }
}
