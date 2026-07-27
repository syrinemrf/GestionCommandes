<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les paramètres, commandes et lignes de commande.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parametre (id INT AUTO_INCREMENT NOT NULL, numero_commande INT DEFAULT 1 NOT NULL, tva NUMERIC(5, 3) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, fournisseur_id INT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(30) NOT NULL, email VARCHAR(255) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, is_deleted TINYINT DEFAULT 0 NOT NULL, INDEX IDX_C7440455946703 (fournisseur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql("CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, numero INT NOT NULL, date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', total_ht NUMERIC(10, 3) NOT NULL, statut VARCHAR(30) DEFAULT 'EN_ATTENTE_CONFIRMATION' NOT NULL, is_deleted TINYINT DEFAULT 0 NOT NULL, user_id INT NOT NULL, fournisseur_id INT NOT NULL, client_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6EEAA67BF55AE19E (numero), INDEX IDX_6EEAA67BA76ED395 (user_id), INDEX IDX_6EEAA67B946703 (fournisseur_id), INDEX IDX_6EEAA67B19EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
        $this->addSql('CREATE TABLE ligne_commande (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, variation_id INT NOT NULL, nom_produit VARCHAR(255) NOT NULL, nom_variation VARCHAR(255) NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 3) NOT NULL, INDEX IDX_3170B74B82EA2E54 (commande_id), INDEX IDX_3170B74BF347EFB (produit_id), INDEX IDX_3170B74B57C53C1 (variation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B57C53C1 FOREIGN KEY (variation_id) REFERENCES product_variation (id)');
        $this->addSql("INSERT INTO parametre (numero_commande, tva) VALUES (1, 19.000)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B57C53C1');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67BA76ED395');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B946703');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B19EB6921');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455946703');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE parametre');
    }
}
