<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les coordonnées client facultatives, la note et la TVA de la commande.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client CHANGE nom nom VARCHAR(255) DEFAULT NULL, CHANGE prenom prenom VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(30) DEFAULT NULL, CHANGE adresse rue LONGTEXT DEFAULT NULL, ADD societe VARCHAR(255) DEFAULT NULL, ADD complement_adresse VARCHAR(255) DEFAULT NULL, ADD ville VARCHAR(150) DEFAULT NULL, ADD code_postal VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD taux_tva NUMERIC(5, 3) DEFAULT 19.000 NOT NULL, ADD note LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP taux_tva, DROP note');
        $this->addSql('ALTER TABLE client CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(30) NOT NULL, CHANGE rue adresse LONGTEXT DEFAULT NULL, DROP societe, DROP complement_adresse, DROP ville, DROP code_postal');
    }
}
