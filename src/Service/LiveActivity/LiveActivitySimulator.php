<?php

namespace App\Service\LiveActivity;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\HistoriqueStatutCommande;
use App\Entity\MouvementStock;
use App\Entity\Parametre;
use App\Entity\ProductVariation;
use App\Entity\User;
use App\Service\CommandeService;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LiveActivitySimulator
{
    private Randomizer $randomizer;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommandeService $commandeService,
        private readonly StockMovementService $stockMovementService,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function simulate(LiveActivityOptions $options): LiveActivityResult
    {
        if ($this->environment === 'prod') {
            throw new \RuntimeException('La simulation d’activité est interdite en production.');
        }

        $catalog = $this->loadCatalog();
        if ($options->orders > 0 && $catalog === []) {
            throw new \RuntimeException('Aucun produit actif avec une variation active n’est disponible.');
        }

        $statusCandidates = $this->loadStatusCandidates();
        $startAt = $this->latestBusinessDate()->modify('+1 minute');
        $result = new LiveActivityResult(
            seed: $options->seed,
            requestedOrders: $options->orders,
            requestedStatusUpdates: $options->statusUpdates,
            startsAt: $startAt,
            dryRun: $options->dryRun,
        );

        if ($options->dryRun) {
            $result->createdOrders = $options->orders;
            $result->updatedStatuses = min($options->statusUpdates, count($statusCandidates));
            $result->endsAt = $startAt->modify(sprintf(
                '+%d minutes',
                max(0, $result->createdOrders + $result->updatedStatuses - 1),
            ));

            return $result;
        }

        $this->randomizer = new Randomizer(new Mt19937($options->seed));
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $clients = $this->loadClientsBySupplier();
            $parameter = $this->entityManager->getRepository(Parametre::class)->findOneBy([])
                ?? throw new \RuntimeException('Les paramètres de commande sont introuvables.');
            $cursor = $startAt;

            for ($index = 0; $index < $options->orders; ++$index) {
                $this->createOrder($catalog, $clients, $parameter, $cursor, $result);
                $cursor = $cursor->modify('+1 minute');
            }

            $this->shuffle($statusCandidates);
            foreach (array_slice($statusCandidates, 0, $options->statusUpdates) as $commande) {
                $nextStatuses = array_values(array_diff(
                    $this->commandeService->getAvailableStatuses($commande),
                    [$commande->getStatut()],
                ));
                if ($nextStatuses === []) {
                    continue;
                }

                $oldStatus = $commande->getStatut();
                $newStatus = $this->pick($nextStatuses);
                $actor = $commande->getFournisseur()
                    ?? throw new \RuntimeException('Une commande sans fournisseur ne peut pas être simulée.');
                $this->commandeService->updateStatus($commande, $newStatus, $actor, $cursor, false);
                $key = $oldStatus.' → '.$newStatus;
                $result->statusChanges[$key] = ($result->statusChanges[$key] ?? 0) + 1;
                ++$result->updatedStatuses;
                $cursor = $cursor->modify('+1 minute');
            }

            $this->entityManager->flush();
            $connection->commit();
            $result->endsAt = $cursor->modify('-1 minute');
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();

            throw $exception;
        }

        return $result;
    }

    /**
     * @param array<int, array{supplier: User, variations: list<ProductVariation>}> $catalog
     * @param array<int, list<Client>> $clients
     */
    private function createOrder(
        array $catalog,
        array $clients,
        Parametre $parameter,
        \DateTimeImmutable $createdAt,
        LiveActivityResult $result,
    ): void {
        $supplierCatalog = $this->pick(array_values($catalog));
        $supplier = $supplierCatalog['supplier'];
        $variations = $supplierCatalog['variations'];
        $lineCount = $this->randomizer->getInt(1, min(3, count($variations)));
        $this->shuffle($variations);

        $commande = (new Commande())
            ->setNumero($parameter->getNumeroCommande())
            ->setDate($createdAt)
            ->setTauxTva($parameter->getTva())
            ->setNote($this->randomizer->getInt(1, 4) === 1 ? 'Commande simulée en activité continue.' : null)
            ->setStatut(Commande::STATUT_EN_ATTENTE_CONFIRMATION)
            ->setUser($supplier)
            ->setFournisseur($supplier)
            ->setIsDeleted(false);
        $parameter->setNumeroCommande($parameter->getNumeroCommande() + 1);

        $supplierClients = $clients[(int) $supplier->getId()] ?? [];
        if ($supplierClients !== [] && $this->randomizer->getInt(1, 10) <= 8) {
            $commande->setClient($this->pick($supplierClients));
        }

        for ($lineIndex = 0; $lineIndex < $lineCount; ++$lineIndex) {
            $variation = $variations[$lineIndex];
            $product = $variation->getProduct()
                ?? throw new \RuntimeException('Une variation sans produit ne peut pas être simulée.');
            $quantity = $this->randomizer->getInt(1, 3);

            if ($variation->getStockDisponible() < $quantity) {
                $this->stockMovementService->reapprovisionner(
                    $variation,
                    $quantity - $variation->getStockDisponible() + $this->randomizer->getInt(5, 15),
                    $supplier,
                    'Réapprovisionnement automatique pour activité simulée',
                    $createdAt->modify('-1 second'),
                );
                ++$result->replenishments;
            }

            $commande->addLigne($this->commandeService->createLigne(
                $product,
                $variation,
                $quantity,
                $supplier,
            ));
            ++$result->createdLines;
        }

        $this->commandeService->recalculateTotal($commande);
        $this->commandeService->initializeNewCommande($commande, $supplier, $createdAt, false);
        ++$result->createdOrders;
    }

    /** @return array<int, array{supplier: User, variations: list<ProductVariation>}> */
    private function loadCatalog(): array
    {
        $variations = $this->entityManager->createQueryBuilder()
            ->select('variation', 'product', 'supplier')
            ->from(ProductVariation::class, 'variation')
            ->innerJoin('variation.product', 'product')
            ->innerJoin('product.fournisseur', 'supplier')
            ->andWhere('variation.isDeleted = false')
            ->andWhere('product.isDeleted = false')
            ->andWhere('supplier.isDeleted = false')
            ->andWhere('supplier.role = :role')
            ->setParameter('role', 'ROLE_FOURNISSEUR')
            ->orderBy('supplier.id', 'ASC')
            ->addOrderBy('variation.id', 'ASC')
            ->getQuery()
            ->getResult();

        $catalog = [];
        foreach ($variations as $variation) {
            $supplier = $variation->getProduct()?->getFournisseur();
            if (!$supplier) {
                continue;
            }
            $supplierId = (int) $supplier->getId();
            $catalog[$supplierId] ??= ['supplier' => $supplier, 'variations' => []];
            $catalog[$supplierId]['variations'][] = $variation;
        }

        return $catalog;
    }

    /** @return array<int, list<Client>> */
    private function loadClientsBySupplier(): array
    {
        $clients = $this->entityManager->getRepository(Client::class)->findBy(
            ['isDeleted' => false],
            ['id' => 'ASC'],
        );
        $bySupplier = [];
        foreach ($clients as $client) {
            $supplierId = (int) $client->getFournisseur()?->getId();
            if ($supplierId > 0) {
                $bySupplier[$supplierId][] = $client;
            }
        }

        return $bySupplier;
    }

    /** @return list<Commande> */
    private function loadStatusCandidates(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('commande', 'supplier')
            ->from(Commande::class, 'commande')
            ->innerJoin('commande.fournisseur', 'supplier')
            ->andWhere('commande.isDeleted = false')
            ->andWhere('supplier.isDeleted = false')
            ->andWhere('commande.statut IN (:statuses)')
            ->setParameter('statuses', [
                Commande::STATUT_EN_ATTENTE_CONFIRMATION,
                Commande::STATUT_EN_PREPARATION,
                Commande::STATUT_PRETE,
                Commande::STATUT_EXPEDIEE,
                Commande::STATUT_EN_LIVRAISON,
            ])
            ->orderBy('commande.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function latestBusinessDate(): \DateTimeImmutable
    {
        $dates = [
            $this->maximum(Commande::class, 'date'),
            $this->maximum(HistoriqueStatutCommande::class, 'changedAt'),
            $this->maximum(MouvementStock::class, 'createdAt'),
        ];
        $dates = array_filter($dates);

        return $dates === []
            ? new \DateTimeImmutable()
            : max($dates);
    }

    private function maximum(string $entityClass, string $property): ?\DateTimeImmutable
    {
        $value = $this->entityManager->createQueryBuilder()
            ->select(sprintf('MAX(entity.%s)', $property))
            ->from($entityClass, 'entity')
            ->getQuery()
            ->getSingleScalarResult();

        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($value)
            : new \DateTimeImmutable((string) $value);
    }

    /** @template T @param list<T> $values @return T */
    private function pick(array $values): mixed
    {
        return $values[$this->randomizer->getInt(0, count($values) - 1)];
    }

    /** @template T @param list<T> $values */
    private function shuffle(array &$values): void
    {
        for ($index = count($values) - 1; $index > 0; --$index) {
            $swap = $this->randomizer->getInt(0, $index);
            [$values[$index], $values[$swap]] = [$values[$swap], $values[$index]];
        }
    }
}
