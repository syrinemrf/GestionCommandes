<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721084604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_variation (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, attributs JSON NOT NULL, prix_supplement NUMERIC(10, 3) NOT NULL, stock INT NOT NULL, reference VARCHAR(100) DEFAULT NULL, is_deleted TINYINT NOT NULL, product_id INT NOT NULL, INDEX IDX_C3B85674584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE product_variation ADD CONSTRAINT FK_C3B85674584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_variation DROP FOREIGN KEY FK_C3B85674584665A');
        $this->addSql('DROP TABLE product_variation');
    }
}
