<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoachProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profil professionnel d'un compte ROLE_COACH (relation 1-1 avec User).
 */
#[ORM\Entity(repositoryClass: CoachProfileRepository::class)]
#[ORM\Index(name: 'idx_coach_profile_city', columns: ['city'])]
class CoachProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'coachProfile')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    /** @var list<string> Ex. ["Yoga", "Pilates"]. */
    #[ORM\Column(type: Types::JSON)]
    private array $specialities = [];

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $experienceYears = null;

    /** DECIMAL restitué en chaîne par Doctrine : pas de perte de précision. */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $hourlyRate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $certifications = null;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'coach')]
    private Collection $sessions;

    /** @var Collection<int, Availability> */
    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'coach', cascade: ['remove'])]
    private Collection $availabilities;

    public function __construct()
    {
        $this->sessions = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
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
        if ($user->getCoachProfile() !== $this) {
            $user->setCoachProfile($this);
        }

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    /** @return list<string> */
    public function getSpecialities(): array
    {
        return $this->specialities;
    }

    /** @param list<string> $specialities */
    public function setSpecialities(array $specialities): static
    {
        $this->specialities = array_values($specialities);

        return $this;
    }

    public function getExperienceYears(): ?int
    {
        return $this->experienceYears;
    }

    public function setExperienceYears(?int $experienceYears): static
    {
        $this->experienceYears = $experienceYears;

        return $this;
    }

    public function getHourlyRate(): ?string
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(?string $hourlyRate): static
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCertifications(): ?string
    {
        return $this->certifications;
    }

    public function setCertifications(?string $certifications): static
    {
        $this->certifications = $certifications;

        return $this;
    }

    /** @return Collection<int, Session> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    /** @return Collection<int, Availability> */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }
}
