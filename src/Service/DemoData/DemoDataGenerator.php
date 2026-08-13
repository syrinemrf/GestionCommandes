<?php

namespace App\Service\DemoData;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\HistoriqueStatutCommande;
use App\Entity\LigneCommande;
use App\Entity\MouvementStock;
use App\Entity\Parametre;
use App\Entity\Product;
use App\Entity\ProductVariation;
use App\Entity\User;
use App\Service\CommandeService;
use App\Service\StockMovementService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Faker\Generator;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DemoDataGenerator
{
    private const DEMO_PASSWORD_HASH = '$2y$10$ss1aK4.U.lCuuGML..Hr3eFEnLyWf.zXr1167PmQKSTe7PnIDhTnu';

    private Generator $faker;
    private Randomizer $randomizer;
    /** @var array<string, array<string, mixed>>|null */
    private ?array $imageManifest = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly DemoCatalog $catalog,
        private readonly DemoDataResetter $resetter,
        private readonly DemandProfile $demandProfile,
        private readonly CommandeService $commandeService,
        private readonly StockMovementService $stockMovementService,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%/public/uploads/demo/products')]
        private readonly string $demoImageDirectory,
    ) {
    }

    public function preview(DemoDataOptions $options): DemoDataResult
    {
        $scale = DemoScale::configuration($options->scale);
        $suppliers = $this->catalog->suppliers($options->scale);
        $products = 0;
        $variations = 0;

        foreach ($suppliers as $supplier) {
            $supplierProducts = $this->catalog->products($supplier, $options->scale);
            $products += count($supplierProducts);
            $variations += count($supplierProducts)
                * count($this->catalog->variations($supplier['key'], $options->scale));
        }

        return new DemoDataResult(
            batchId: $options->batchId(),
            suppliers: count($suppliers),
            clients: $scale['clients'],
            products: $products,
            variations: $variations,
            orders: $scale['orders'],
            lines: (int) round($scale['orders'] * 2.5),
            movements: $scale['orders'] * 5,
            statusTransitions: (int) round($scale['orders'] * 5.5),
            minDate: $options->startDate,
            maxDate: $options->endDate,
            dryRun: true,
        );
    }

    public function generate(DemoDataOptions $options): DemoDataResult
    {
        if ($this->environment === 'prod') {
            throw new \RuntimeException('La génération de données de démonstration est interdite en production.');
        }

        if ($options->dryRun) {
            return $this->preview($options);
        }

        $startedAt = microtime(true);
        $batchId = $options->batchId();

        if (!$options->resetDemo && $this->resetter->batchExists($batchId)) {
            $result = $this->summarize($batchId);
            $result->alreadyExists = true;
            $result->durationSeconds = microtime(true) - $startedAt;

            return $result;
        }

        if (!$options->resetDemo && $this->resetter->hasDemoData()) {
            throw new \RuntimeException(
                'Un autre jeu de démonstration existe déjà. Relancez avec --reset-demo pour le remplacer.'
            );
        }

        $this->faker = Factory::create('fr_FR');
        $this->faker->seed($options->seed);
        $this->randomizer = new Randomizer(new Mt19937($options->seed));

        $this->connection->beginTransaction();

        try {
            if ($options->resetDemo) {
                $this->resetter->resetAll();
            }

            [$supplierIds, $productMeta, $variationIds, $clientIds] =
                $this->createCatalogAndClients($options, $batchId);

            $events = $this->createOrders(
                $options,
                $batchId,
                $supplierIds,
                $productMeta,
                $clientIds,
            );
            $stockoutVariationIds = $this->processEvents(
                $events,
                $variationIds,
                $options,
                $batchId,
            );

            $this->updateNextOrderNumber();
            $this->entityManager->flush();
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->entityManager->clear();

            throw $exception;
        }

        $result = $this->summarize($batchId);
        $result->stockoutVariations = count($stockoutVariationIds);
        $result->durationSeconds = microtime(true) - $startedAt;

        return $result;
    }

    /** @return array{list<int>, array<int, list<array<string, mixed>>>, list<int>, array<int, list<int>>} */
    private function createCatalogAndClients(
        DemoDataOptions $options,
        string $batchId,
    ): array {
        $supplierIds = [];
        $productMeta = [];
        $variationIds = [];
        $clientsBySupplier = [];
        $suppliers = $this->catalog->suppliers($options->scale);
        $startAt = $options->startDate->setTime(0, 0);

        foreach ($suppliers as $supplierIndex => $definition) {
            $supplier = (new User())
                ->setNom('Équipe')
                ->setPrenom((string) $definition['name'])
                ->setLibelle((string) $definition['name'])
                ->setEmail(sprintf('%s@demo.comdely.test', $definition['key']))
                ->setRole('ROLE_FOURNISSEUR')
                ->setIsDeleted(false)
                ->setDemoBatch($batchId);
            $supplier->setPassword(self::DEMO_PASSWORD_HASH);
            $this->entityManager->persist($supplier);
            $this->entityManager->flush();
            $supplierId = (int) $supplier->getId();
            $supplierIds[] = $supplierId;
            $productMeta[$supplierId] = [];
            $clientsBySupplier[$supplierId] = [];

            foreach ($this->catalog->products($definition, $options->scale) as $productIndex => $productDefinition) {
                $product = (new Product())
                    ->setLibelle((string) $productDefinition['name'])
                    ->setDescription((string) $productDefinition['description'])
                    ->setPrix(number_format((float) $productDefinition['price_ht'], 3, '.', ''))
                    ->setFournisseur($supplier)
                    ->setImage($this->resolveProductImage((string) $productDefinition['key']))
                    ->setIsDeleted(false)
                    ->setCreatedAt($options->startDate)
                    ->setDemoBatch($batchId);
                $this->entityManager->persist($product);

                $productVariationIds = [];
                foreach ($this->catalog->variations((string) $definition['key'], $options->scale) as $variationIndex => $variationDefinition) {
                    $stock = 55 + $this->deterministicInt(
                        $options->seed,
                        [
                            (string) $definition['key'],
                            (string) $productDefinition['key'],
                            (string) $variationIndex,
                        ],
                        81,
                    );
                    $variation = (new ProductVariation())
                        ->setProduct($product)
                        ->setLibelle((string) $variationDefinition['label'])
                        ->setAttributs((array) $variationDefinition['attributes'])
                        ->setPrixSupplement(number_format((float) $variationDefinition['price_extra'], 3, '.', ''))
                        ->setReference(sprintf('DEMO-%02d-%02d-%02d', $supplierIndex + 1, $productIndex + 1, $variationIndex + 1))
                        ->setStock($stock)
                        ->setStockUtilise(0)
                        ->setStockReserve(0)
                        ->setIsDeleted(false);
                    $product->addVariation($variation);
                    $this->entityManager->persist($variation);
                    $this->stockMovementService->enregistrerStockInitial(
                        $variation,
                        $supplier,
                        $startAt,
                        $batchId,
                    );
                    $this->entityManager->flush();
                    $variationId = (int) $variation->getId();
                    $variationIds[] = $variationId;
                    $productVariationIds[] = $variationId;
                }

                $productMeta[$supplierId][] = [
                    'id' => (int) $product->getId(),
                    'profile' => (string) $productDefinition['demand_profile'],
                    'variations' => $productVariationIds,
                ];
            }
        }

        $clientCount = DemoScale::configuration($options->scale)['clients'];
        $clientProfiles = $this->createClientProfiles($clientCount, $options->seed);
        $cities = [
            ['Tunis', '1000'], ['Ariana', '2080'], ['Ben Arous', '2013'],
            ['Nabeul', '8000'], ['Sousse', '4000'], ['Monastir', '5000'],
            ['Sfax', '3000'], ['Bizerte', '7000'],
        ];

        for ($index = 0; $index < $clientCount; ++$index) {
            $supplierId = $supplierIds[$index % count($supplierIds)];
            $supplier = $this->entityManager->find(User::class, $supplierId);
            $city = $cities[$this->randomInt(0, count($cities) - 1)];
            $profile = $clientProfiles[$index];
            $client = (new Client())
                ->setFournisseur($supplier)
                ->setNom($profile['last_name'])
                ->setPrenom($profile['first_name'])
                ->setSociete($index % 7 === 0 ? 'Atelier Démo '.$index : null)
                ->setTelephone(sprintf('+216 %02d %03d %03d', $this->randomInt(20, 99), $this->randomInt(0, 999), $this->randomInt(0, 999)))
                ->setEmail(sprintf('client.%03d@demo.comdely.test', $index + 1))
                ->setRue(sprintf('%d rue %s', $this->randomInt(1, 180), $profile['street_name']))
                ->setVille($city[0])
                ->setCodePostal($city[1])
                ->setIsDeleted(false)
                ->setDemoBatch($batchId);
            $this->entityManager->persist($client);
            $this->entityManager->flush();
            $clientsBySupplier[$supplierId][] = (int) $client->getId();
        }

        $this->entityManager->clear();

        return [$supplierIds, $productMeta, $variationIds, $clientsBySupplier];
    }

    /**
     * @param list<int> $supplierIds
     * @param array<int, list<array<string, mixed>>> $productMeta
     * @param array<int, list<int>> $clientIds
     * @return list<array<string, mixed>>
     */
    private function createOrders(
        DemoDataOptions $options,
        string $batchId,
        array $supplierIds,
        array $productMeta,
        array $clientIds,
    ): array {
        $configuration = DemoScale::configuration($options->scale);
        $events = [];
        $pending = [];
        $nextNumber = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(numero), 0) + 1 FROM commande');
        $tva = $this->connection->fetchOne('SELECT tva FROM parametre ORDER BY id LIMIT 1');
        if ($tva === false) {
            throw new \LogicException('Les paramètres de commande sont introuvables.');
        }

        for ($index = 0; $index < $configuration['orders']; ++$index) {
            $date = match ($index) {
                0 => $options->startDate->setTime(10, 0),
                $configuration['orders'] - 1 => $options->endDate->setTime(10, 0),
                default => $this->randomOrderDate($options),
            };
            $supplierId = $supplierIds[$this->randomInt(0, count($supplierIds) - 1)];
            /** @var User $supplier */
            $supplier = $this->entityManager->find(User::class, $supplierId);
            $commande = (new Commande())
                ->setNumero($nextNumber++)
                ->setDate($date)
                ->setTauxTva(number_format((float) $tva, 3, '.', ''))
                ->setNote($index % 11 === 0 ? 'Commande synthétique pour analyse.' : null)
                ->setStatut(Commande::STATUT_EN_ATTENTE_CONFIRMATION)
                ->setUser($supplier)
                ->setFournisseur($supplier)
                ->setIsDeleted(false)
                ->setDemoBatch($batchId);

            if ($index % 10 !== 0) {
                $availableClients = $clientIds[$supplierId];
                $clientId = $availableClients[$this->randomInt(0, count($availableClients) - 1)];
                $commande->setClient($this->entityManager->find(Client::class, $clientId));
            }

            $lineCount = $this->randomInt(1, 4);
            $usedVariations = [];
            $returnCandidate = null;
            for ($lineIndex = 0; $lineIndex < $lineCount; ++$lineIndex) {
                $meta = $this->pickProduct($productMeta[$supplierId], $date, $options);
                $availableVariations = array_values(array_diff($meta['variations'], $usedVariations));
                if ($availableVariations === []) {
                    --$lineIndex;
                    continue;
                }
                $variationId = $availableVariations[$this->randomInt(0, count($availableVariations) - 1)];
                $usedVariations[] = $variationId;
                /** @var Product $product */
                $product = $this->entityManager->find(Product::class, $meta['id']);
                /** @var ProductVariation $variation */
                $variation = $this->entityManager->find(ProductVariation::class, $variationId);
                $quantity = $this->randomInt(1, $meta['profile'] === 'high' ? 5 : 3);
                $ligne = $this->commandeService->createLigne($product, $variation, $quantity, $supplier);
                $commande->addLigne($ligne);
                $returnCandidate ??= ['variation_id' => $variationId, 'quantity' => 1];
            }
            $this->commandeService->recalculateTotal($commande);
            $this->entityManager->persist($commande);
            $pending[] = [
                'commande' => $commande,
                'events' => $this->buildOrderEvents($date, $options->endDate, $returnCandidate),
            ];

            if (count($pending) >= $configuration['batch_size'] || $index === $configuration['orders'] - 1) {
                $this->entityManager->flush();
                foreach ($pending as $plan) {
                    foreach ($plan['events'] as $event) {
                        $event->commandeId = (int) $plan['commande']->getId();
                        $events[] = $event;
                    }
                }
                $pending = [];
                $this->entityManager->clear();
                gc_collect_cycles();
                gc_mem_caches();
            }
        }

        return $events;
    }

    /** @param list<int> $variationIds @param list<array<string, mixed>> $events @return array<int, true> */
    private function processEvents(
        array $events,
        array $variationIds,
        DemoDataOptions $options,
        string $batchId,
    ): array {
        $adjustmentCount = $options->scale === 'small' ? 4 : 80;
        for ($index = 0; $index < $adjustmentCount; ++$index) {
            $events[] = new DemoEvent(
                type: 'adjustment',
                timestamp: $this->randomDateTime($options->startDate, $options->endDate)->getTimestamp(),
                variationId: $variationIds[$this->randomInt(0, count($variationIds) - 1)],
            );
        }

        foreach ($events as $sequence => &$event) {
            $event->sequence = $sequence;
        }
        unset($event);

        usort($events, static function (DemoEvent $left, DemoEvent $right): int {
            $dateComparison = $left->timestamp <=> $right->timestamp;

            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            $priorityComparison = self::eventPriority($left->type) <=> self::eventPriority($right->type);

            return $priorityComparison !== 0
                ? $priorityComparison
                : $left->sequence <=> $right->sequence;
        });

        $stockouts = [];
        $batchSize = DemoScale::configuration($options->scale)['batch_size'];
        foreach ($events as $index => $event) {
            $eventAt = (new \DateTimeImmutable('@'.$event->timestamp))
                ->setTimezone($options->startDate->getTimezone());
            if ($event->type === 'adjustment') {
                $this->processAdjustment($event, $eventAt, $batchId);
            } else {
                /** @var Commande|null $commande */
                $commande = $this->entityManager->find(Commande::class, $event->commandeId);
                if (!$commande) {
                    throw new \RuntimeException('Une commande de démonstration est introuvable.');
                }
                /** @var User $actor */
                $actor = $commande->getFournisseur();

                if ($event->type === 'created') {
                    foreach ($this->quantitiesByVariation($commande) as $variationId => $quantity) {
                        /** @var ProductVariation $variation */
                        $variation = $this->entityManager->find(ProductVariation::class, $variationId);
                        if ($variation->getStockDisponible() < $quantity) {
                            $stockouts[$variationId] = true;
                            $needed = $quantity - $variation->getStockDisponible();
                            $buffer = 30 + $this->deterministicInt(
                                $options->seed,
                                [
                                    (string) $variation->getReference(),
                                    $eventAt->format(DATE_ATOM),
                                ],
                                51,
                            );
                            $this->stockMovementService->reapprovisionner(
                                $variation,
                                $needed + $buffer,
                                $actor,
                                'Réapprovisionnement automatique de démonstration après seuil de rupture',
                                $eventAt->modify('-1 second'),
                                $batchId,
                            );
                        }
                    }
                    $this->commandeService->initializeNewCommande($commande, $actor, $eventAt, false);
                } elseif ($event->type === 'status') {
                    $this->commandeService->updateStatus($commande, (string) $event->status, $actor, $eventAt, false);
                } elseif ($event->type === 'return') {
                    /** @var ProductVariation $variation */
                    $variation = $this->entityManager->find(ProductVariation::class, $event->variationId);
                    if ($variation->getStockUtilise() > 0) {
                        $this->stockMovementService->retournerStock(
                            $variation,
                            min($event->quantity, $variation->getStockUtilise()),
                            $actor,
                            $commande,
                            'Retour client synthétique',
                            $eventAt,
                            $batchId,
                        );
                    }
                }
            }

            if (($index + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                gc_collect_cycles();
                gc_mem_caches();
            }

            unset($events[$index]);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $stockouts;
    }

    /** @return list<array<string, mixed>> */
    private function buildOrderEvents(
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $endDate,
        ?array $returnCandidate,
    ): array {
        $events = [new DemoEvent('created', $createdAt->getTimestamp())];
        $cursor = $createdAt;
        $cancelled = $this->randomInt(1, 100) <= 8;

        if ($cancelled && $this->randomBool()) {
            $cancelAt = $cursor->modify(sprintf('+%d hours', $this->randomInt(2, 30)));
            if ($cancelAt <= $endDate) {
                $events[] = new DemoEvent('status', $cancelAt->getTimestamp(), status: Commande::STATUT_ANNULEE);
            }

            return $events;
        }

        $steps = [
            [Commande::STATUT_EN_PREPARATION, 3, 36],
            [Commande::STATUT_PRETE, 12, 96],
            [Commande::STATUT_EXPEDIEE, 2, 36],
            [Commande::STATUT_EN_LIVRAISON, 2, 30],
            [Commande::STATUT_LIVREE, 8, 72],
        ];

        foreach ($steps as $stepIndex => [$status, $minimumHours, $maximumHours]) {
            $cursor = $cursor->modify(sprintf('+%d hours', $this->randomInt($minimumHours, $maximumHours)));
            if ($cursor > $endDate) {
                break;
            }
            $events[] = new DemoEvent('status', $cursor->getTimestamp(), status: $status);

            if ($cancelled && $status === Commande::STATUT_EN_PREPARATION) {
                $cancelAt = $cursor->modify(sprintf('+%d hours', $this->randomInt(1, 24)));
                if ($cancelAt <= $endDate) {
                    $events[] = new DemoEvent('status', $cancelAt->getTimestamp(), status: Commande::STATUT_ANNULEE);
                }
                break;
            }

            if (
                $status === Commande::STATUT_LIVREE
                && $returnCandidate !== null
                && $this->randomInt(1, 100) <= 6
            ) {
                $returnAt = $cursor->modify(sprintf('+%d days', $this->randomInt(1, 14)));
                if ($returnAt <= $endDate) {
                    $events[] = new DemoEvent(
                        type: 'return',
                        timestamp: $returnAt->getTimestamp(),
                        variationId: $returnCandidate['variation_id'],
                        quantity: $returnCandidate['quantity'],
                    );
                }
            }
        }

        return $events;
    }

    /** @param list<array<string, mixed>> $products @return array<string, mixed> */
    private function pickProduct(array $products, \DateTimeImmutable $date, DemoDataOptions $options): array
    {
        $weights = [];
        $total = 0.0;
        foreach ($products as $index => $product) {
            $total += $this->demandProfile->weight($product['profile'], $date, $options->startDate, $options->endDate);
            $weights[$index] = $total;
        }
        $draw = ($this->randomInt(0, 1_000_000) / 1_000_000) * $total;
        foreach ($weights as $index => $upperBound) {
            if ($draw <= $upperBound) {
                return $products[$index];
            }
        }

        return $products[array_key_last($products)];
    }

    private function randomOrderDate(DemoDataOptions $options): \DateTimeImmutable
    {
        $date = $this->randomDateTime($options->startDate, $options->endDate);
        if ((int) $date->format('N') === 7) {
            $candidate = $date->modify('+1 day');
            $date = $candidate <= $options->endDate ? $candidate : $date->modify('-1 day');
        }

        return $date;
    }

    private function randomDateTime(\DateTimeImmutable $start, \DateTimeImmutable $end): \DateTimeImmutable
    {
        $timestamp = $this->randomInt($start->getTimestamp(), $end->getTimestamp());

        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone($start->getTimezone());
    }

    /** @return array<int, int> */
    private function quantitiesByVariation(Commande $commande): array
    {
        $quantities = [];
        foreach ($commande->getLignes() as $ligne) {
            $variationId = (int) $ligne->getVariation()?->getId();
            $quantities[$variationId] = ($quantities[$variationId] ?? 0) + $ligne->getQuantite();
        }

        return $quantities;
    }

    private function processAdjustment(
        DemoEvent $event,
        \DateTimeImmutable $eventAt,
        string $batchId,
    ): void
    {
        /** @var ProductVariation|null $variation */
        $variation = $this->entityManager->find(ProductVariation::class, $event->variationId);
        if (!$variation) {
            return;
        }
        /** @var User $actor */
        $actor = $variation->getProduct()?->getFournisseur();
        $minimum = $variation->getStockUtilise() + $variation->getStockReserve();
        $newStock = $variation->getStock() > $minimum && $this->randomBool()
            ? $variation->getStock() - 1
            : $variation->getStock() + 1;
        $this->stockMovementService->ajusterStock(
            $variation,
            $newStock,
            $actor,
            'Ajustement d’inventaire synthétique',
            $eventAt,
            $batchId,
        );
    }

    private static function eventPriority(string $type): int
    {
        return match ($type) {
            'adjustment' => 0,
            'created' => 1,
            'status' => 2,
            'return' => 3,
            default => 4,
        };
    }

    private function resolveProductImage(string $key): ?string
    {
        $manifest = $this->imageManifest();
        $manifestFile = isset($manifest[$key]['local_file'])
            ? basename((string) $manifest[$key]['local_file'])
            : null;
        if ($manifestFile !== null && is_file($this->demoImageDirectory.'/'.$manifestFile)) {
            return '../demo/products/'.$manifestFile;
        }

        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $extension) {
            if (is_file($this->demoImageDirectory.'/'.$key.'.'.$extension)) {
                return '../demo/products/'.$key.'.'.$extension;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    private function imageManifest(): array
    {
        if ($this->imageManifest !== null) {
            return $this->imageManifest;
        }

        $path = $this->demoImageDirectory.'/manifest.json';
        if (!is_file($path)) {
            return $this->imageManifest = [];
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return $this->imageManifest = is_array($manifest) ? $manifest : [];
        } catch (\JsonException) {
            return $this->imageManifest = [];
        }
    }

    private function updateNextOrderNumber(): void
    {
        $nextNumber = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(numero), 0) + 1 FROM commande');
        $parametre = $this->entityManager->getRepository(Parametre::class)->findOneBy([]);
        if ($parametre && $parametre->getNumeroCommande() < $nextNumber) {
            $parametre->setNumeroCommande($nextNumber);
        }
    }

    private function summarize(string $batchId): DemoDataResult
    {
        $result = new DemoDataResult(batchId: $batchId);
        $result->suppliers = $this->count('user', $batchId);
        $result->clients = $this->count('client', $batchId);
        $result->products = $this->count('product', $batchId);
        $result->variations = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_variation v INNER JOIN product p ON p.id = v.product_id WHERE p.demo_batch = ?',
            [$batchId],
        );
        $result->orders = $this->count('commande', $batchId);
        $result->lines = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ligne_commande l INNER JOIN commande c ON c.id = l.commande_id WHERE c.demo_batch = ?',
            [$batchId],
        );
        $result->movements = $this->count('mouvement_stock', $batchId);
        $result->statusTransitions = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM historique_statut_commande h INNER JOIN commande c ON c.id = h.commande_id WHERE c.demo_batch = ?',
            [$batchId],
        );
        $dates = $this->connection->fetchAssociative(
            'SELECT MIN(date) AS min_date, MAX(date) AS max_date FROM commande WHERE demo_batch = ?',
            [$batchId],
        );
        $result->minDate = isset($dates['min_date']) ? new \DateTimeImmutable($dates['min_date']) : null;
        $result->maxDate = isset($dates['max_date']) ? new \DateTimeImmutable($dates['max_date']) : null;
        foreach ($this->connection->fetchAllAssociative(
            'SELECT statut, COUNT(*) AS total FROM commande WHERE demo_batch = ? GROUP BY statut ORDER BY statut',
            [$batchId],
        ) as $row) {
            $result->ordersByStatus[$row['statut']] = (int) $row['total'];
        }
        $result->stockoutVariations = (int) $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT variation_id) FROM mouvement_stock WHERE demo_batch = ? AND type = ? AND commentaire LIKE 'Réapprovisionnement automatique%'",
            [$batchId, MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT],
        );

        return $result;
    }

    private function count(string $table, string $batchId): int
    {
        $allowed = ['user', 'client', 'product', 'commande', 'mouvement_stock'];
        if (!in_array($table, $allowed, true)) {
            throw new \LogicException('Table de synthèse non autorisée.');
        }

        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE demo_batch = ?', $table),
            [$batchId],
        );
    }

    /** @return list<array{first_name: string, last_name: string, street_name: string}> */
    private function createClientProfiles(int $count, int $seed): array
    {
        $this->faker->seed($seed + 17);
        $profiles = [];

        for ($index = 0; $index < $count; ++$index) {
            $profiles[] = [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'street_name' => $this->faker->streetName(),
            ];
        }

        return $profiles;
    }

    private function randomInt(int $minimum, int $maximum): int
    {
        return $this->randomizer->getInt($minimum, $maximum);
    }

    private function randomBool(): bool
    {
        return $this->randomInt(0, 1) === 1;
    }

    /** @param list<string> $parts */
    private function deterministicInt(int $seed, array $parts, int $modulo): int
    {
        $hash = hash('sha256', $seed.'|'.implode('|', $parts));

        return (int) (hexdec(substr($hash, 0, 8)) % $modulo);
    }
}
