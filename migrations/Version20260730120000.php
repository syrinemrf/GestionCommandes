<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les réclamations du support et leurs messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, objet VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, categorie VARCHAR(30) NOT NULL, priorite VARCHAR(20) NOT NULL, statut VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, fournisseur_id INT NOT NULL, admin_assigne_id INT DEFAULT NULL, INDEX IDX_CE606404670C757F (fournisseur_id), INDEX IDX_CE60640446452C35 (admin_assigne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE reclamation_message (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, created_at DATETIME NOT NULL, reclamation_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_6797EEEE2D6BA2D9 (reclamation_id), INDEX IDX_6797EEEE60BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404670C757F FOREIGN KEY (fournisseur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE60640446452C35 FOREIGN KEY (admin_assigne_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reclamation_message ADD CONSTRAINT FK_6797EEEE2D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamation (id)');
        $this->addSql('ALTER TABLE reclamation_message ADD CONSTRAINT FK_6797EEEE60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reclamation_message DROP FOREIGN KEY FK_6797EEEE2D6BA2D9');
        $this->addSql('ALTER TABLE reclamation_message DROP FOREIGN KEY FK_6797EEEE60BB6FE6');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404670C757F');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE60640446452C35');
        $this->addSql('DROP TABLE reclamation_message');
        $this->addSql('DROP TABLE reclamation');
    }
}
