<?php

namespace App\Entity;

use App\Repository\HistoriqueStatutCommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueStatutCommandeRepository::class)]
#[ORM\Index(name: 'idx_historique_statut_changed_at', columns: ['changed_at'])]
class HistoriqueStatutCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ancienStatut = null;

    #[ORM\Column(length: 30)]
    private string $nouveauStatut;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $changedBy = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getAncienStatut(): ?string
    {
        return $this->ancienStatut;
    }

    public function setAncienStatut(?string $ancienStatut): static
    {
        $this->ancienStatut = $ancienStatut;

        return $this;
    }

    public function getNouveauStatut(): string
    {
        return $this->nouveauStatut;
    }

    public function setNouveauStatut(string $nouveauStatut): static
    {
        $this->nouveauStatut = $nouveauStatut;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): static
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }
}
