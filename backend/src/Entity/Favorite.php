<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TargetType;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Favori d'un utilisateur : exactement une cible parmi salle, coach et séance
 * (CHECK en base). Un seul favori par utilisateur et par cible.
 */
#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_favorite_user_gym', columns: ['user_id', 'gym_id'])]
#[ORM\UniqueConstraint(name: 'uq_favorite_user_coach', columns: ['user_id', 'coach_id'])]
#[ORM\UniqueConstraint(name: 'uq_favorite_user_session', columns: ['user_id', 'session_id'])]
class Favorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'favorites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Gym::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Gym $gym = null;

    #[ORM\ManyToOne(targetEntity: CoachProfile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CoachProfile $coach = null;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Session $session = null;

    #[ORM\Column(length: 20, enumType: TargetType::class)]
    private ?TargetType $type = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

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

    public function getGym(): ?Gym
    {
        return $this->gym;
    }

    public function getCoach(): ?CoachProfile
    {
        return $this->coach;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function getType(): ?TargetType
    {
        return $this->type;
    }

    /**
     * Définit la cible et son type en une seule fois : impossible d'avoir deux cibles.
     */
    public function setTarget(Gym|CoachProfile|Session $target): static
    {
        $this->gym = $target instanceof Gym ? $target : null;
        $this->coach = $target instanceof CoachProfile ? $target : null;
        $this->session = $target instanceof Session ? $target : null;
        $this->type = match (true) {
            $target instanceof Gym => TargetType::Gym,
            $target instanceof CoachProfile => TargetType::Coach,
            default => TargetType::Session,
        };

        return $this;
    }

    public function getTarget(): Gym|CoachProfile|Session|null
    {
        return $this->gym ?? $this->coach ?? $this->session;
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
}
