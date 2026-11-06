<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte de la plateforme : sportif, coach, gestionnaire ou administrateur.
 *
 * Le rôle est stocké dans `roles` (JSON). `isVerified` passe à vrai quand l'adresse e-mail
 * est confirmée ; un compte non vérifié ou bloqué ne peut pas se connecter (RG-02).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_SPORTIF = 'ROLE_SPORTIF';
    public const ROLE_COACH = 'ROLE_COACH';
    public const ROLE_GESTIONNAIRE = 'ROLE_GESTIONNAIRE';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /** Hash du mot de passe (jamais le mot de passe en clair). */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBlocked = false;

    /** Langue des e-mails et notifications (fr ou en), reprise de la dernière requête du compte. */
    #[ORM\Column(length: 2, options: ['default' => 'fr'])]
    private string $locale = 'fr';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: CoachProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?CoachProfile $coachProfile = null;

    /** @var Collection<int, Gym> Salles gérées (gestionnaire). */
    #[ORM\OneToMany(targetEntity: Gym::class, mappedBy: 'manager')]
    private Collection $managedGyms;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'user')]
    private Collection $reservations;

    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(targetEntity: Favorite::class, mappedBy: 'user')]
    private Collection $favorites;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'author')]
    private Collection $reviews;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->managedGyms = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** Identifiant de connexion utilisé par Symfony Security. */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * Rôles stockés, sans doublon. Un compte sans rôle est traité comme un sportif :
     * la hiérarchie (ROLE_ADMIN > tous) est définie dans security.yaml.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique($this->roles ?: [self::ROLE_SPORTIF]));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->getRoles(), true);
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): static
    {
        $this->isBlocked = $isBlocked;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /** Toute langue autre que l'anglais retombe sur le français (langue par défaut). */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale === 'en' ? 'en' : 'fr';

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

    public function getCoachProfile(): ?CoachProfile
    {
        return $this->coachProfile;
    }

    public function setCoachProfile(?CoachProfile $coachProfile): static
    {
        // Garde les deux côtés de la relation 1-1 synchronisés.
        if ($coachProfile !== null && $coachProfile->getUser() !== $this) {
            $coachProfile->setUser($this);
        }
        $this->coachProfile = $coachProfile;

        return $this;
    }

    /** @return Collection<int, Gym> */
    public function getManagedGyms(): Collection
    {
        return $this->managedGyms;
    }

    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    /** @return Collection<int, Favorite> */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /** Aucune donnée sensible temporaire n'est conservée sur l'objet. */
    public function eraseCredentials(): void
    {
    }
}
