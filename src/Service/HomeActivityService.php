<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\HistoriqueStatutCommande;
use App\Entity\MouvementStock;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\HistoriqueStatutCommandeRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ProductRepository;

class HomeActivityService
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProductRepository $productRepository,
        private readonly MouvementStockRepository $mouvementRepository,
        private readonly HistoriqueStatutCommandeRepository $historiqueRepository,
    ) {
    }

    /**
     * @return array{
     *     activities: array<int, array<string, mixed>>
     * }
     */
    public function build(User $user, bool $admin): array
    {
        $supplier = $admin ? null : $user;
        $recentOrders = $this->commandeRepository
            ->findRecentForHome($supplier, 5);
        $recentProducts = $this->productRepository
            ->findRecentForHome($supplier, 5);
        $recentMovements = $this->mouvementRepository
            ->findRecentForHome($supplier, 5);
        $recentStatusChanges = $this->historiqueRepository
            ->findRecentForHome($supplier, 5);

        return [
            'activities' => $this->mergeActivities(
                $recentOrders,
                $recentProducts,
                $recentMovements,
                $recentStatusChanges,
            ),
        ];
    }

    /**
     * @param Commande[] $orders
     * @param Product[] $products
     * @param MouvementStock[] $movements
     * @param HistoriqueStatutCommande[] $statusChanges
     * @return array<int, array<string, mixed>>
     */
    private function mergeActivities(
        array $orders,
        array $products,
        array $movements,
        array $statusChanges,
    ): array {
        $activities = [];

        foreach ($orders as $order) {
            $activities[] = [
                'type' => 'order',
                'icon' => 'receipt',
                'title' => sprintf(
                    'Commande CMD-%s enregistrée',
                    $order->getNumero(),
                ),
                'detail' => $order->getClient()
                    ? trim(sprintf(
                        '%s %s',
                        $order->getClient()->getPrenom(),
                        $order->getClient()->getNom(),
                    ))
                    : 'Sans client renseigné',
                'date' => $order->getDate(),
                'route' => 'commande_list',
            ];
        }

        foreach ($products as $product) {
            $activities[] = [
                'type' => 'product',
                'icon' => 'box-seam',
                'title' => sprintf(
                    'Produit « %s » ajouté',
                    $product->getLibelle(),
                ),
                'detail' => $product->getFournisseur()?->getLibelle()
                    ?: 'Catalogue produits',
                'date' => $product->getCreatedAt(),
                'route' => 'product_list',
            ];
        }

        foreach ($movements as $movement) {
            $product = $movement->getVariation()?->getProduct();
            $activities[] = [
                'type' => 'stock',
                'icon' => $movement->getType()
                    === MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT
                    ? 'box-arrow-in-down'
                    : 'arrow-left-right',
                'title' => $this->movementLabel($movement),
                'detail' => sprintf(
                    '%s%s',
                    $product?->getLibelle() ?: 'Produit',
                    $movement->getVariation()?->getLibelle()
                        ? ' - ' . $movement->getVariation()->getLibelle()
                        : '',
                ),
                'date' => $movement->getCreatedAt(),
                'route' => 'product_list',
            ];
        }

        foreach ($statusChanges as $statusChange) {
            $order = $statusChange->getCommande();
            $activities[] = [
                'type' => 'status',
                'icon' => 'arrow-repeat',
                'title' => sprintf(
                    'Statut de CMD-%s mis à jour',
                    $order?->getNumero(),
                ),
                'detail' => $this->statusLabel(
                    $statusChange->getNouveauStatut(),
                ),
                'date' => $statusChange->getChangedAt(),
                'route' => 'commande_list',
            ];
        }

        usort(
            $activities,
            static fn (array $left, array $right): int =>
                $right['date'] <=> $left['date'],
        );

        return array_slice($activities, 0, 6);
    }

    private function movementLabel(MouvementStock $movement): string
    {
        $labels = [
            MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT =>
                'Réapprovisionnement enregistré',
            MouvementStock::TYPE_RESERVATION_COMMANDE =>
                'Stock réservé pour une commande',
            MouvementStock::TYPE_LIBERATION_RESERVATION =>
                'Réservation de stock libérée',
            MouvementStock::TYPE_SORTIE_COMMANDE =>
                'Sortie de stock enregistrée',
            MouvementStock::TYPE_RETOUR =>
                'Retour en stock enregistré',
            MouvementStock::TYPE_AJUSTEMENT =>
                'Stock ajusté',
        ];

        return sprintf(
            '%s (%d unité%s)',
            $labels[$movement->getType()] ?? 'Mouvement de stock',
            $movement->getQuantite(),
            $movement->getQuantite() > 1 ? 's' : '',
        );
    }

    private function statusLabel(string $status): string
    {
        return [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION =>
                'En attente de confirmation',
            Commande::STATUT_EN_PREPARATION => 'En préparation',
            Commande::STATUT_PRETE => 'Prête',
            Commande::STATUT_EXPEDIEE => 'Expédiée',
            Commande::STATUT_EN_LIVRAISON => 'En livraison',
            Commande::STATUT_LIVREE => 'Livrée',
            Commande::STATUT_ANNULEE => 'Annulée',
        ][$status] ?? $status;
    }
}
