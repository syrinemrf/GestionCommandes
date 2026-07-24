<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le stock utilisé aux variations de produit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variation ADD stock_utilise INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variation DROP stock_utilise');
    }
}
