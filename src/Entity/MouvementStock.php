<?php

namespace App\Entity;

use App\Repository\MouvementStockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
#[ORM\Index(name: 'idx_mouvement_stock_type', columns: ['type'])]
#[ORM\Index(name: 'idx_mouvement_stock_created_at', columns: ['created_at'])]
class MouvementStock
{
    public const TYPE_ENTREE_APPROVISIONNEMENT = 'ENTREE_APPROVISIONNEMENT';
    public const TYPE_RESERVATION_COMMANDE = 'RESERVATION_COMMANDE';
    public const TYPE_LIBERATION_RESERVATION = 'LIBERATION_RESERVATION';
    public const TYPE_SORTIE_COMMANDE = 'SORTIE_COMMANDE';
    public const TYPE_RETOUR = 'RETOUR';
    public const TYPE_AJUSTEMENT = 'AJUSTEMENT';

    public const TYPES = [
        self::TYPE_ENTREE_APPROVISIONNEMENT,
        self::TYPE_RESERVATION_COMMANDE,
        self::TYPE_LIBERATION_RESERVATION,
        self::TYPE_SORTIE_COMMANDE,
        self::TYPE_RETOUR,
        self::TYPE_AJUSTEMENT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductVariation $variation = null;

    #[ORM\Column(length: 40)]
    private string $type;

    #[ORM\Column]
    private int $quantite;

    #[ORM\Column]
    private int $stockAvant;

    #[ORM\Column]
    private int $stockApres;

    #[ORM\Column]
    private int $stockReserveAvant;

    #[ORM\Column]
    private int $stockReserveApres;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariation(): ?ProductVariation
    {
        return $this->variation;
    }

    public function setVariation(?ProductVariation $variation): static
    {
        $this->variation = $variation;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type de mouvement invalide.');
        }

        $this->type = $type;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getStockAvant(): int
    {
        return $this->stockAvant;
    }

    public function setStockAvant(int $stockAvant): static
    {
        $this->stockAvant = $stockAvant;

        return $this;
    }

    public function getStockApres(): int
    {
        return $this->stockApres;
    }

    public function setStockApres(int $stockApres): static
    {
        $this->stockApres = $stockApres;

        return $this;
    }

    public function getStockReserveAvant(): int
    {
        return $this->stockReserveAvant;
    }

    public function setStockReserveAvant(int $stockReserveAvant): static
    {
        $this->stockReserveAvant = $stockReserveAvant;

        return $this;
    }

    public function getStockReserveApres(): int
    {
        return $this->stockReserveApres;
    }

    public function setStockReserveApres(int $stockReserveApres): static
    {
        $this->stockReserveApres = $stockReserveApres;

        return $this;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}
