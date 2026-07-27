<?php

namespace App\Entity;

use App\Repository\ParametreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametreRepository::class)]
class Parametre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $numeroCommande = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 3)]
    private string $tva = '19.000';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroCommande(): int
    {
        return $this->numeroCommande;
    }

    public function setNumeroCommande(int $numeroCommande): static
    {
        $this->numeroCommande = $numeroCommande;

        return $this;
    }

    public function getTva(): string
    {
        return $this->tva;
    }

    public function setTva(string $tva): static
    {
        $this->tva = $tva;

        return $this;
    }
}
