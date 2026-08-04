<?php

namespace App\Service\DemoData;

use Doctrine\DBAL\Connection;

final readonly class DemoDataResetter
{
    public function __construct(private Connection $connection)
    {
    }

    public function hasDemoData(): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM commande WHERE demo_batch IS NOT NULL'
        ) > 0 || (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user WHERE demo_batch IS NOT NULL'
        ) > 0;
    }

    public function batchExists(string $batchId): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM commande WHERE demo_batch = ?',
            [$batchId],
        ) > 0;
    }

    public function resetAll(): void
    {
        $this->connection->executeStatement(
            'DELETE h FROM historique_statut_commande h INNER JOIN commande c ON c.id = h.commande_id WHERE c.demo_batch IS NOT NULL'
        );
        $this->connection->executeStatement(
            'DELETE FROM mouvement_stock WHERE demo_batch IS NOT NULL'
        );
        $this->connection->executeStatement(
            'DELETE l FROM ligne_commande l INNER JOIN commande c ON c.id = l.commande_id WHERE c.demo_batch IS NOT NULL'
        );
        $this->connection->executeStatement('DELETE FROM commande WHERE demo_batch IS NOT NULL');
        $this->connection->executeStatement('DELETE FROM client WHERE demo_batch IS NOT NULL');
        $this->connection->executeStatement(
            'DELETE v FROM product_variation v INNER JOIN product p ON p.id = v.product_id WHERE p.demo_batch IS NOT NULL'
        );
        $this->connection->executeStatement('DELETE FROM product WHERE demo_batch IS NOT NULL');
        $this->connection->executeStatement('DELETE FROM user WHERE demo_batch IS NOT NULL');
    }
}
