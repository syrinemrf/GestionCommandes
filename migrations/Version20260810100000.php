<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the product creation timestamp used by incremental analytics and ML';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD created_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE product SET created_at = CASE WHEN demo_batch IS NOT NULL THEN '2024-08-04 00:00:00' ELSE COALESCE((SELECT MIN(c.date) FROM ligne_commande lc INNER JOIN commande c ON c.id = lc.commande_id WHERE lc.produit_id = product.id), CURRENT_TIMESTAMP) END WHERE created_at IS NULL");
        $this->addSql('ALTER TABLE product MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP created_at');
    }
}
