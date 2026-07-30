<?php

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
class Reclamation
{
    public const CATEGORIE_COMMANDE = 'COMMANDE';
    public const CATEGORIE_PRODUIT = 'PRODUIT';
    public const CATEGORIE_STOCK = 'STOCK';
    public const CATEGORIE_COMPTE = 'COMPTE';
    public const CATEGORIE_TECHNIQUE = 'TECHNIQUE';
    public const CATEGORIE_AUTRE = 'AUTRE';

    public const PRIORITE_NORMALE = 'NORMALE';
    public const PRIORITE_IMPORTANTE = 'IMPORTANTE';
    public const PRIORITE_URGENTE = 'URGENTE';

    public const STATUT_OUVERTE = 'OUVERTE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_EN_ATTENTE_FOURNISSEUR = 'EN_ATTENTE_FOURNISSEUR';
    public const STATUT_RESOLUE = 'RESOLUE';
    public const STATUT_FERMEE = 'FERMEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $fournisseur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $adminAssigne = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L’objet de la demande est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $objet = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description de la demande est obligatoire.')]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [
        self::CATEGORIE_COMMANDE,
        self::CATEGORIE_PRODUIT,
        self::CATEGORIE_STOCK,
        self::CATEGORIE_COMPTE,
        self::CATEGORIE_TECHNIQUE,
        self::CATEGORIE_AUTRE,
    ])]
    private string $categorie = self::CATEGORIE_AUTRE;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [
        self::PRIORITE_NORMALE,
        self::PRIORITE_IMPORTANTE,
        self::PRIORITE_URGENTE,
    ])]
    private string $priorite = self::PRIORITE_NORMALE;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [
        self::STATUT_OUVERTE,
        self::STATUT_EN_COURS,
        self::STATUT_EN_ATTENTE_FOURNISSEUR,
        self::STATUT_RESOLUE,
        self::STATUT_FERMEE,
    ])]
    private string $statut = self::STATUT_OUVERTE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * @var Collection<int, ReclamationMessage>
     */
    #[ORM\OneToMany(
        mappedBy: 'reclamation',
        targetEntity: ReclamationMessage::class,
        cascade: ['persist']
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAdminAssigne(): ?User
    {
        return $this->adminAssigne;
    }

    public function setAdminAssigne(?User $adminAssigne): static
    {
        $this->adminAssigne = $adminAssigne;

        return $this;
    }

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(string $objet): static
    {
        $this->objet = $objet;

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

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getPriorite(): string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): static
    {
        $this->priorite = $priorite;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    /**
     * @return Collection<int, ReclamationMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ReclamationMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setReclamation($this);
        }

        return $this;
    }
}
