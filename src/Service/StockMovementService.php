<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\MouvementStock;
use App\Entity\ProductVariation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class StockMovementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function enregistrerStockInitial(
        ProductVariation $variation,
        User $actor,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $demoBatch = null,
    ): void {
        if ($variation->getStock() <= 0) {
            return;
        }

        $this->record(
            $variation,
            MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT,
            $variation->getStock(),
            0,
            $variation->getStockPhysique(),
            0,
            $variation->getStockReserve(),
            $actor,
            null,
            'Stock initial',
            $occurredAt,
            $demoBatch,
        );
    }

    public function reapprovisionner(
        ProductVariation $variation,
        int $quantite,
        User $actor,
        ?string $commentaire = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $demoBatch = null,
    ): void {
        $this->assertPositiveQuantity($quantite);

        $stockAvant = $variation->getStockPhysique();
        $reserveAvant = $variation->getStockReserve();
        $variation->setStock($variation->getStock() + $quantite);

        $this->record(
            $variation,
            MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT,
            $quantite,
            $stockAvant,
            $variation->getStockPhysique(),
            $reserveAvant,
            $variation->getStockReserve(),
            $actor,
            null,
            $commentaire,
            $occurredAt,
            $demoBatch,
        );
    }

    public function ajusterStock(
        ProductVariation $variation,
        int $nouveauStock,
        User $actor,
        ?string $commentaire = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $demoBatch = null,
    ): void {
        if ($nouveauStock < 0) {
            throw new \DomainException(
                'Le stock doit être positif ou égal à zéro.'
            );
        }

        if (
            $nouveauStock
            < $variation->getStockUtilise() + $variation->getStockReserve()
        ) {
            throw new \DomainException(
                'Le stock ne peut pas être inférieur aux quantités sorties et réservées.'
            );
        }

        $ancienStock = $variation->getStock();

        if ($nouveauStock === $ancienStock) {
            return;
        }

        $stockAvant = $variation->getStockPhysique();
        $reserveAvant = $variation->getStockReserve();
        $variation->setStock($nouveauStock);

        $this->record(
            $variation,
            MouvementStock::TYPE_AJUSTEMENT,
            abs($nouveauStock - $ancienStock),
            $stockAvant,
            $variation->getStockPhysique(),
            $reserveAvant,
            $variation->getStockReserve(),
            $actor,
            null,
            $commentaire,
            $occurredAt,
            $demoBatch,
        );
    }

    public function reserverCommande(
        Commande $commande,
        User $actor,
        ?\DateTimeImmutable $occurredAt = null,
    ): void
    {
        $stocks = $this->getQuantitesParVariation($commande);

        foreach ($stocks as $stockCommande) {
            if (
                $stockCommande['quantite']
                > $stockCommande['variation']->getStockDisponible()
            ) {
                throw new \DomainException(
                    'Stock insuffisant pour '
                    . $stockCommande['variation']->getLibelle()
                    . '.'
                );
            }
        }

        foreach ($stocks as $stockCommande) {
            $variation = $stockCommande['variation'];
            $quantite = $stockCommande['quantite'];
            $stockAvant = $variation->getStockPhysique();
            $reserveAvant = $variation->getStockReserve();

            $variation->setStockReserve($reserveAvant + $quantite);

            $this->record(
                $variation,
                MouvementStock::TYPE_RESERVATION_COMMANDE,
                $quantite,
                $stockAvant,
                $variation->getStockPhysique(),
                $reserveAvant,
                $variation->getStockReserve(),
                $actor,
                $commande,
                null,
                $occurredAt,
                $commande->getDemoBatch(),
            );
        }
    }

    public function libererReservation(
        Commande $commande,
        User $actor,
        ?\DateTimeImmutable $occurredAt = null,
    ): void {
        foreach ($this->getQuantitesParVariation($commande) as $stockCommande) {
            $variation = $stockCommande['variation'];
            $quantite = min(
                $stockCommande['quantite'],
                $variation->getStockReserve()
            );

            if ($quantite === 0) {
                continue;
            }

            $stockAvant = $variation->getStockPhysique();
            $reserveAvant = $variation->getStockReserve();
            $variation->setStockReserve($reserveAvant - $quantite);

            $this->record(
                $variation,
                MouvementStock::TYPE_LIBERATION_RESERVATION,
                $quantite,
                $stockAvant,
                $variation->getStockPhysique(),
                $reserveAvant,
                $variation->getStockReserve(),
                $actor,
                $commande,
                null,
                $occurredAt,
                $commande->getDemoBatch(),
            );
        }
    }

    public function sortirCommande(
        Commande $commande,
        User $actor,
        ?\DateTimeImmutable $occurredAt = null,
    ): void
    {
        $stocks = $this->getQuantitesParVariation($commande);

        foreach ($stocks as $stockCommande) {
            if (
                $stockCommande['quantite']
                > $stockCommande['variation']->getStockReserve()
            ) {
                throw new \DomainException(
                    'La réservation de stock est insuffisante pour cette commande.'
                );
            }
        }

        foreach ($stocks as $stockCommande) {
            $variation = $stockCommande['variation'];
            $quantite = $stockCommande['quantite'];
            $stockAvant = $variation->getStockPhysique();
            $reserveAvant = $variation->getStockReserve();

            $variation
                ->setStockReserve($reserveAvant - $quantite)
                ->setStockUtilise(
                    $variation->getStockUtilise() + $quantite
                );

            $this->record(
                $variation,
                MouvementStock::TYPE_SORTIE_COMMANDE,
                $quantite,
                $stockAvant,
                $variation->getStockPhysique(),
                $reserveAvant,
                $variation->getStockReserve(),
                $actor,
                $commande,
                null,
                $occurredAt,
                $commande->getDemoBatch(),
            );
        }
    }

    public function retournerStock(
        ProductVariation $variation,
        int $quantite,
        User $actor,
        ?Commande $commande = null,
        ?string $commentaire = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $demoBatch = null,
    ): void {
        $this->assertPositiveQuantity($quantite);

        if ($quantite > $variation->getStockUtilise()) {
            throw new \DomainException(
                'La quantité retournée dépasse la quantité sortie.'
            );
        }

        $stockAvant = $variation->getStockPhysique();
        $reserveAvant = $variation->getStockReserve();
        $variation->setStockUtilise(
            $variation->getStockUtilise() - $quantite
        );

        $this->record(
            $variation,
            MouvementStock::TYPE_RETOUR,
            $quantite,
            $stockAvant,
            $variation->getStockPhysique(),
            $reserveAvant,
            $variation->getStockReserve(),
            $actor,
            $commande,
            $commentaire,
            $occurredAt,
            $demoBatch ?? $commande?->getDemoBatch(),
        );
    }

    /**
     * @return array<int, array{
     *     variation: ProductVariation,
     *     quantite: int
     * }>
     */
    private function getQuantitesParVariation(Commande $commande): array
    {
        $stocks = [];

        foreach ($commande->getLignes() as $ligne) {
            $variation = $ligne->getVariation();

            if (!$variation) {
                continue;
            }

            $key = $variation->getId() ?? spl_object_id($variation);

            if (!isset($stocks[$key])) {
                $stocks[$key] = [
                    'variation' => $variation,
                    'quantite' => 0,
                ];
            }

            $stocks[$key]['quantite'] += $ligne->getQuantite();
        }

        return $stocks;
    }

    private function record(
        ProductVariation $variation,
        string $type,
        int $quantite,
        int $stockAvant,
        int $stockApres,
        int $reserveAvant,
        int $reserveApres,
        User $actor,
        ?Commande $commande = null,
        ?string $commentaire = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $demoBatch = null,
    ): void {
        $commentaire = trim((string) $commentaire);

        $mouvement = (new MouvementStock())
            ->setVariation($variation)
            ->setType($type)
            ->setQuantite($quantite)
            ->setStockAvant($stockAvant)
            ->setStockApres($stockApres)
            ->setStockReserveAvant($reserveAvant)
            ->setStockReserveApres($reserveApres)
            ->setCommande($commande)
            ->setCreatedBy($actor)
            ->setCommentaire($commentaire !== '' ? $commentaire : null)
            ->setCreatedAt($occurredAt ?? new \DateTimeImmutable())
            ->setDemoBatch($demoBatch);

        $this->entityManager->persist($mouvement);
    }

    private function assertPositiveQuantity(int $quantite): void
    {
        if ($quantite <= 0) {
            throw new \DomainException(
                'La quantité doit être supérieure à zéro.'
            );
        }
    }
}
