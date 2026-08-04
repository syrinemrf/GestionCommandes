<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marque précisément les données synthétiques afin de pouvoir les réinitialiser sans toucher aux données réelles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD demo_batch VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD demo_batch VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD demo_batch VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD demo_batch VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_stock ADD demo_batch VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_user_demo_batch ON user (demo_batch)');
        $this->addSql('CREATE INDEX idx_product_demo_batch ON product (demo_batch)');
        $this->addSql('CREATE INDEX idx_client_demo_batch ON client (demo_batch)');
        $this->addSql('CREATE INDEX idx_commande_demo_batch ON commande (demo_batch)');
        $this->addSql('CREATE INDEX idx_mouvement_demo_batch ON mouvement_stock (demo_batch)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_user_demo_batch ON user');
        $this->addSql('DROP INDEX idx_product_demo_batch ON product');
        $this->addSql('DROP INDEX idx_client_demo_batch ON client');
        $this->addSql('DROP INDEX idx_commande_demo_batch ON commande');
        $this->addSql('DROP INDEX idx_mouvement_demo_batch ON mouvement_stock');
        $this->addSql('ALTER TABLE user DROP demo_batch');
        $this->addSql('ALTER TABLE product DROP demo_batch');
        $this->addSql('ALTER TABLE client DROP demo_batch');
        $this->addSql('ALTER TABLE commande DROP demo_batch');
        $this->addSql('ALTER TABLE mouvement_stock DROP demo_batch');
    }
}
