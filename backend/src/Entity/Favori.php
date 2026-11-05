<?php

namespace App\Entity;

use App\Entity\Activite;
use App\Entity\Coach;
use App\Entity\Salle;
use App\Repository\FavoriRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriRepository::class)]
class Favori
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_ajout', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateAjout = null;

    #[ORM\Column(name: 'datetime_immutable', length: 255)]
    private ?string $legacyDateTime = null;

    #[ORM\ManyToOne(inversedBy: 'favoris')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'favoris')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Seance $seance = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Salle $salle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Coach $coach = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Activite $activite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateAjout(): ?\DateTimeImmutable { return $this->dateAjout; }
    public function setDateAjout(\DateTimeImmutable $value): static { $this->dateAjout = $value; return $this; }

    public function getLegacyDateTime(): ?string { return $this->legacyDateTime; }
    public function setLegacyDateTime(?string $value): static { $this->legacyDateTime = $value; return $this; }
    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getSalle(): ?Salle { return $this->salle; }
    public function setSalle(?Salle $value): static { $this->salle = $value; return $this; }
    public function getCoach(): ?Coach { return $this->coach; }
    public function setCoach(?Coach $value): static { $this->coach = $value; return $this; }
    public function getActivite(): ?Activite { return $this->activite; }
    public function setActivite(?Activite $value): static { $this->activite = $value; return $this; }

    public function getSeance(): ?Seance
    {
        return $this->seance;
    }

    public function setSeance(?Seance $seance): static
    {
        $this->seance = $seance;

        return $this;
    }
}
