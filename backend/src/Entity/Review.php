<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis noté (1 à 5) sur une salle, un coach ou une séance — exactement une cible (CHECK en base).
 *
 * Publié immédiatement ; l'administrateur le valide (`isModerated`) ou le masque
 * (`isVisible = false`), voir RG-08. Un seul avis par auteur et par cible (RG-07).
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_review_author_gym', columns: ['author_id', 'gym_id'])]
#[ORM\UniqueConstraint(name: 'uq_review_author_coach', columns: ['author_id', 'coach_id'])]
#[ORM\UniqueConstraint(name: 'uq_review_author_session', columns: ['author_id', 'session_id'])]
class Review
{
    public const RATING_MIN = 1;
    public const RATING_MAX = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Gym::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Gym $gym = null;

    #[ORM\ManyToOne(targetEntity: CoachProfile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CoachProfile $coach = null;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Session $session = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = self::RATING_MAX;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isModerated = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isVisible = true;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

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

    /** Définit l'unique cible de l'avis. */
    public function setTarget(Gym|CoachProfile|Session $target): static
    {
        $this->gym = $target instanceof Gym ? $target : null;
        $this->coach = $target instanceof CoachProfile ? $target : null;
        $this->session = $target instanceof Session ? $target : null;

        return $this;
    }

    public function getTarget(): Gym|CoachProfile|Session|null
    {
        return $this->gym ?? $this->coach ?? $this->session;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function isModerated(): bool
    {
        return $this->isModerated;
    }

    public function setIsModerated(bool $isModerated): static
    {
        $this->isModerated = $isModerated;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

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
}
