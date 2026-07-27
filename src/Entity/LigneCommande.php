<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $produit = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du produit est obligatoire.')]
    private ?string $nomProduit = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductVariation $variation = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la variation est obligatoire.')]
    private ?string $nomVariation = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La quantité doit être supérieure à zéro.')]
    private int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $prixUnitaire = '0.000';

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

    public function getProduit(): ?Product
    {
        return $this->produit;
    }

    public function setProduit(?Product $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getNomProduit(): ?string
    {
        return $this->nomProduit;
    }

    public function setNomProduit(string $nomProduit): static
    {
        $this->nomProduit = $nomProduit;

        return $this;
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

    public function getNomVariation(): ?string
    {
        return $this->nomVariation;
    }

    public function setNomVariation(string $nomVariation): static
    {
        $this->nomVariation = $nomVariation;

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

    public function getPrixUnitaire(): string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getTotalHt(): string
    {
        return number_format(
            (float) $this->prixUnitaire * $this->quantite,
            3,
            '.',
            ''
        );
    }
}
