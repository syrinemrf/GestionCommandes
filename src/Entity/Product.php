<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $libelle = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,3})?$/', message: 'Le prix doit être un nombre avec au maximum 3 décimales.')]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou égal à zéro.')]
    private ?string $prix = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_fournisseur_id', referencedColumnName: 'id', nullable: false)]
    private ?User $fournisseur = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isDeleted = false;

    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductVariation::class
    )]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $variations;

    public function __construct()
    {
        $this->variations = new ArrayCollection();
    }

    /**
     * @return Collection<int, ProductVariation>
     */
    public function getVariations(): Collection
    {
        return $this->variations;
    }

    public function addVariation(ProductVariation $variation): static
    {
        if (!$this->variations->contains($variation)) {
            $this->variations->add($variation);
            $variation->setProduct($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getFournisseur(): ?User
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?User $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(?bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getStockTotal(): int
    {
        $total = 0;

        foreach ($this->variations as $variation) {
            if (!$variation->isDeleted()) {
                $total += $variation->getStock();
            }
        }

        return $total;
    }

    /**
     * @return ProductVariation[]
     */
    public function getActiveVariations(): array
    {
        return $this->variations
            ->filter(
                static fn (ProductVariation $variation): bool =>
                    !$variation->isDeleted()
            )
            ->toArray();
    }
}
