<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[UniqueEntity(fields: ['numero'], message: 'Ce numéro de commande est déjà utilisé.')]
class Commande
{
    public const STATUT_EN_ATTENTE_CONFIRMATION = 'EN_ATTENTE_CONFIRMATION';
    public const STATUT_EN_PREPARATION = 'EN_PREPARATION';
    public const STATUT_PRETE = 'PRETE';
    public const STATUT_EXPEDIEE = 'EXPEDIEE';
    public const STATUT_EN_LIVRAISON = 'EN_LIVRAISON';
    public const STATUT_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private ?int $numero = null;

    #[ORM\Column]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    #[Assert\PositiveOrZero(message: 'Le total HT doit être positif ou égal à zéro.')]
    private string $totalHt = '0.000';

    #[ORM\Column(length: 30, options: ['default' => self::STATUT_EN_ATTENTE_CONFIRMATION])]
    private string $statut = self::STATUT_EN_ATTENTE_CONFIRMATION;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(
        mappedBy: 'commande',
        targetEntity: LigneCommande::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $lignes;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTotalHt(): string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): static
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneCommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }

        return $this;
    }

    public function removeLigne(LigneCommande $ligne): static
    {
        if (
            $this->lignes->removeElement($ligne)
            && $ligne->getCommande() === $this
        ) {
            $ligne->setCommande(null);
        }

        return $this;
    }
}
