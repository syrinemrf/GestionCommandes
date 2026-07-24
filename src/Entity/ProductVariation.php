<?php

namespace App\Entity;

use App\Repository\ProductVariationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductVariationRepository::class)]
class ProductVariation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé de la variation est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::JSON)]
    private array $attributs = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    #[Assert\NotBlank(message: 'Le supplément de prix est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d+(?:\.\d{1,3})?$/',
        message: 'Le supplément doit être un nombre avec au maximum 3 décimales.'
    )]
    #[Assert\PositiveOrZero(
        message: 'Le supplément de prix doit être positif ou égal à zéro.'
    )]
    private string $prixSupplement = '0.000';

    #[ORM\Column]
    #[Assert\PositiveOrZero(
        message: 'Le stock doit être positif ou égal à zéro.'
    )]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero(
        message: 'Le stock utilisé doit être positif ou égal à zéro.'
    )]
    private int $stockUtilise = 0;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $reference = null;

    #[ORM\Column]
    private bool $isDeleted = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getAttributs(): array
    {
        return $this->attributs;
    }

    public function setAttributs(array $attributs): static
    {
        $this->attributs = $attributs;

        return $this;
    }

    public function getPrixSupplement(): string
    {
        return $this->prixSupplement;
    }

    public function setPrixSupplement(string $prixSupplement): static
    {
        $this->prixSupplement = $prixSupplement;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getStockUtilise(): int
    {
        return $this->stockUtilise;
    }

    public function setStockUtilise(int $stockUtilise): static
    {
        $this->stockUtilise = $stockUtilise;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}
