<?php

namespace App\Tests\DemoData;

use App\Entity\Commande;
use App\Entity\MouvementStock;
use App\Entity\Parametre;
use App\Entity\User;
use App\Service\DemoData\DemoDataGenerator;
use App\Service\DemoData\DemoDataOptions;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DemoDataGeneratorTest extends KernelTestCase
{
    private static EntityManagerInterface $entityManager;
    private static Connection $connection;
    private static DemoDataGenerator $generator;
    /** @var array<string, mixed> */
    private static array $firstSnapshot;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::$connection = self::$entityManager->getConnection();
        $database = (string) self::$connection->fetchOne('SELECT DATABASE()');
        self::assertStringEndsWith('_test', $database, 'Le test d’intégration doit utiliser exclusivement la base de test.');

        $metadata = self::$entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool(self::$entityManager);
        self::$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$connection->createSchemaManager()->listTableNames() as $tableName) {
            self::$connection->executeStatement(sprintf('DROP TABLE `%s`', str_replace('`', '``', $tableName)));
        }
        self::$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $schemaTool->createSchema($metadata);
        self::$entityManager->persist((new Parametre())->setNumeroCommande(1)->setTva('19.000'));
        self::$entityManager->flush();
        self::$generator = static::getContainer()->get(DemoDataGenerator::class);
    }

    public function testSmallDatasetRespecteLesInvariantsMetierEtTemporels(): void
    {
        $result = self::$generator->generate($this->options(reset: true));

        self::assertSame(2, $result->suppliers);
        self::assertSame(30, $result->clients);
        self::assertSame(120, $result->orders);
        self::assertSame('2024-08-04', $result->minDate?->format('Y-m-d'));
        self::assertSame('2026-08-04', $result->maxDate?->format('Y-m-d'));
        self::assertGreaterThan(0, $result->stockoutVariations);

        $this->assertDatesAndPartialAugust();
        $this->assertOrderHistoryAndWorkflow();
        $this->assertStocksAndMovements();
        $this->assertPricesAndSupplierIsolation();
        self::$firstSnapshot = $this->snapshot();
    }

    #[Depends('testSmallDatasetRespecteLesInvariantsMetierEtTemporels')]
    public function testSameSeedDoesNotCreateDuplicates(): void
    {
        $result = self::$generator->generate($this->options());

        self::assertTrue($result->alreadyExists);
        self::assertSame(self::$firstSnapshot, $this->snapshot());
    }

    #[Depends('testSameSeedDoesNotCreateDuplicates')]
    public function testResetIsExclusiveAndGenerationIsReproducible(): void
    {
        $manual = (new User())
            ->setNom('Utilisateur')
            ->setPrenom('Manuel')
            ->setEmail('manuel@example.test')
            ->setPassword('not-used-in-test')
            ->setRole('ROLE_ADMIN')
            ->setIsDeleted(false);
        self::$entityManager->persist($manual);
        self::$entityManager->flush();

        self::$generator->generate($this->options(reset: true));

        self::assertSame(1, (int) self::$connection->fetchOne(
            'SELECT COUNT(*) FROM user WHERE email = ? AND demo_batch IS NULL',
            ['manuel@example.test'],
        ));
        self::assertSame(self::$firstSnapshot, $this->snapshot());
    }

    private function assertDatesAndPartialAugust(): void
    {
        self::assertSame(0, (int) self::$connection->fetchOne(
            "SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL AND date > '2026-08-04 23:59:59'"
        ));
        self::assertGreaterThan(0, (int) self::$connection->fetchOne(
            "SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL AND date >= '2026-08-01' AND date < '2026-08-05'"
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            "SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL AND date >= '2026-08-05' AND date < '2026-09-01'"
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            "SELECT COUNT(*) FROM historique_statut_commande h INNER JOIN commande c ON c.id=h.commande_id WHERE c.demo_batch IS NOT NULL AND (h.changed_at < c.date OR h.changed_at > '2026-08-04 23:59:59')"
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            "SELECT COUNT(*) FROM mouvement_stock WHERE demo_batch IS NOT NULL AND (created_at < '2024-08-04 00:00:00' OR created_at > '2026-08-04 23:59:59')"
        ));
    }

    private function assertOrderHistoryAndWorkflow(): void
    {
        $allowed = [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION => [Commande::STATUT_EN_PREPARATION, Commande::STATUT_ANNULEE],
            Commande::STATUT_EN_PREPARATION => [Commande::STATUT_PRETE, Commande::STATUT_ANNULEE],
            Commande::STATUT_PRETE => [Commande::STATUT_EXPEDIEE],
            Commande::STATUT_EXPEDIEE => [Commande::STATUT_EN_LIVRAISON],
            Commande::STATUT_EN_LIVRAISON => [Commande::STATUT_LIVREE],
        ];
        $histories = self::$connection->fetchAllAssociative(
            'SELECT h.commande_id, h.ancien_statut, h.nouveau_statut, h.changed_at FROM historique_statut_commande h INNER JOIN commande c ON c.id=h.commande_id WHERE c.demo_batch IS NOT NULL ORDER BY h.commande_id, h.changed_at, h.id'
        );
        $previous = [];
        $previousDate = [];
        foreach ($histories as $history) {
            $orderId = (int) $history['commande_id'];
            if (!isset($previous[$orderId])) {
                self::assertNull($history['ancien_statut']);
                self::assertSame(Commande::STATUT_EN_ATTENTE_CONFIRMATION, $history['nouveau_statut']);
            } else {
                self::assertSame($previous[$orderId], $history['ancien_statut']);
                self::assertContains($history['nouveau_statut'], $allowed[$history['ancien_statut']] ?? []);
                self::assertGreaterThanOrEqual($previousDate[$orderId], $history['changed_at']);
            }
            $previous[$orderId] = $history['nouveau_statut'];
            $previousDate[$orderId] = $history['changed_at'];
        }
    }

    private function assertStocksAndMovements(): void
    {
        $types = array_column(self::$connection->fetchAllAssociative(
            'SELECT DISTINCT type FROM mouvement_stock WHERE demo_batch IS NOT NULL'
        ), 'type');
        foreach (MouvementStock::TYPES as $type) {
            self::assertContains($type, $types);
        }

        $movements = self::$connection->fetchAllAssociative(
            'SELECT variation_id, stock_avant, stock_apres, stock_reserve_avant, stock_reserve_apres FROM mouvement_stock WHERE demo_batch IS NOT NULL ORDER BY variation_id, created_at, id'
        );
        $last = [];
        foreach ($movements as $movement) {
            $variationId = (int) $movement['variation_id'];
            if (isset($last[$variationId])) {
                self::assertSame($last[$variationId]['stock'], (int) $movement['stock_avant']);
                self::assertSame($last[$variationId]['reserved'], (int) $movement['stock_reserve_avant']);
            }
            self::assertGreaterThanOrEqual(0, (int) $movement['stock_apres']);
            self::assertGreaterThanOrEqual(0, (int) $movement['stock_reserve_apres']);
            self::assertGreaterThanOrEqual((int) $movement['stock_reserve_apres'], (int) $movement['stock_apres']);
            $last[$variationId] = [
                'stock' => (int) $movement['stock_apres'],
                'reserved' => (int) $movement['stock_reserve_apres'],
            ];
        }

        $variations = self::$connection->fetchAllAssociative(
            'SELECT v.id, v.stock-v.stock_utilise AS physical, v.stock_reserve AS reserved, v.stock-v.stock_utilise-v.stock_reserve AS available FROM product_variation v INNER JOIN product p ON p.id=v.product_id WHERE p.demo_batch IS NOT NULL'
        );
        foreach ($variations as $variation) {
            self::assertGreaterThanOrEqual(0, (int) $variation['physical']);
            self::assertGreaterThanOrEqual(0, (int) $variation['reserved']);
            self::assertGreaterThanOrEqual(0, (int) $variation['available']);
            self::assertSame((int) $variation['physical'] - (int) $variation['reserved'], (int) $variation['available']);
            self::assertSame($last[(int) $variation['id']]['stock'], (int) $variation['physical']);
            self::assertSame($last[(int) $variation['id']]['reserved'], (int) $variation['reserved']);
        }
    }

    private function assertPricesAndSupplierIsolation(): void
    {
        self::assertSame(0, (int) self::$connection->fetchOne(
            'SELECT COUNT(*) FROM commande c WHERE c.demo_batch IS NOT NULL AND ABS(c.total_ht - (SELECT SUM(l.prix_unitaire*l.quantite) FROM ligne_commande l WHERE l.commande_id=c.id)) > 0.0005'
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            'SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL AND ABS((total_ht*(1+taux_tva/100))-ROUND(total_ht*(1+taux_tva/100), 3)) > 0.0005'
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            'SELECT COUNT(*) FROM ligne_commande l INNER JOIN commande c ON c.id=l.commande_id INNER JOIN product p ON p.id=l.produit_id INNER JOIN product_variation v ON v.id=l.variation_id WHERE c.demo_batch IS NOT NULL AND (c.fournisseur_id<>p.id_fournisseur_id OR v.product_id<>p.id)'
        ));
        self::assertSame(0, (int) self::$connection->fetchOne(
            'SELECT COUNT(*) FROM commande c INNER JOIN client cl ON cl.id=c.client_id WHERE c.demo_batch IS NOT NULL AND c.fournisseur_id<>cl.fournisseur_id'
        ));
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'entities' => self::$connection->fetchAssociative(
                'SELECT (SELECT COUNT(*) FROM user WHERE demo_batch IS NOT NULL) suppliers, (SELECT COUNT(*) FROM client WHERE demo_batch IS NOT NULL) clients, (SELECT COUNT(*) FROM product WHERE demo_batch IS NOT NULL) products, (SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL) orders'
            ),
            'statuses' => self::$connection->fetchAllAssociative(
                'SELECT statut, COUNT(*) total FROM commande WHERE demo_batch IS NOT NULL GROUP BY statut ORDER BY statut'
            ),
            'movements' => self::$connection->fetchAllAssociative(
                'SELECT type, COUNT(*) total, SUM(quantite) quantity FROM mouvement_stock WHERE demo_batch IS NOT NULL GROUP BY type ORDER BY type'
            ),
            'days' => self::$connection->fetchAllAssociative(
                'SELECT DATE(date) day, COUNT(*) total FROM commande WHERE demo_batch IS NOT NULL GROUP BY DATE(date) ORDER BY DATE(date)'
            ),
        ];
    }

    private function options(bool $reset = false): DemoDataOptions
    {
        return new DemoDataOptions(
            20260804,
            new \DateTimeImmutable('2024-08-04 00:00:00'),
            new \DateTimeImmutable('2026-08-04 23:59:59'),
            'small',
            resetDemo: $reset,
        );
    }
}
