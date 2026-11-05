<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GymRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Salle de sport, gérée par un compte ROLE_GESTIONNAIRE.
 */
#[ORM\Entity(repositoryClass: GymRepository::class)]
#[ORM\Index(name: 'idx_gym_city', columns: ['city'])]
class Gym
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Gestionnaire : une salle ne peut pas exister sans lui (RESTRICT en base). */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'managedGyms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $manager = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    /** @var array<string, string>|null Ex. {"lundi": "07:00-22:00"}. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $openingHours = null;

    /** @var list<string> URL ou noms de fichier des photos. */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'gym')]
    private Collection $sessions;

    /** @var Collection<int, Availability> */
    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'gym', cascade: ['remove'])]
    private Collection $availabilities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->sessions = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(User $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** @return array<string, string>|null */
    public function getOpeningHours(): ?array
    {
        return $this->openingHours;
    }

    /** @param array<string, string>|null $openingHours */
    public function setOpeningHours(?array $openingHours): static
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    /** @return list<string> */
    public function getPhotos(): array
    {
        return $this->photos;
    }

    /** @param list<string> $photos */
    public function setPhotos(array $photos): static
    {
        $this->photos = array_values($photos);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
