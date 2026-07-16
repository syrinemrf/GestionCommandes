<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend les emails uniques et stocke les prix avec une précision décimale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('ALTER TABLE product MODIFY prix NUMERIC(10, 3) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('ALTER TABLE product MODIFY prix DOUBLE PRECISION NOT NULL');
    }
}
