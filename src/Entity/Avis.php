<?php

namespace App\Entity;

use App\Entity\Activite;
use App\Entity\Coach;
use App\Entity\Salle;
use App\Repository\AvisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $note = null;

    #[ORM\Column(length: 255)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateAvis = null;

    #[ORM\ManyToOne(inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'avis')]
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

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDateAvis(): ?\DateTimeImmutable
    {
        return $this->dateAvis;
    }

    public function setDateAvis(\DateTimeImmutable $dateAvis): static
    {
        $this->dateAvis = $dateAvis;

        return $this;
    }

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
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $value): static { $this->statut = $value; return $this; }

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
