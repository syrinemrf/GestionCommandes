<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le stock réservé, l’historique des statuts et les mouvements de stock.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variation ADD stock_reserve INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE historique_statut_commande (id INT AUTO_INCREMENT NOT NULL, ancien_statut VARCHAR(30) DEFAULT NULL, nouveau_statut VARCHAR(30) NOT NULL, changed_at DATETIME NOT NULL, commande_id INT NOT NULL, changed_by_id INT NOT NULL, INDEX IDX_CAB4641E82EA2E54 (commande_id), INDEX IDX_CAB4641E828AD0A0 (changed_by_id), INDEX idx_historique_statut_changed_at (changed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE mouvement_stock (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(40) NOT NULL, quantite INT NOT NULL, stock_avant INT NOT NULL, stock_apres INT NOT NULL, stock_reserve_avant INT NOT NULL, stock_reserve_apres INT NOT NULL, created_at DATETIME NOT NULL, commentaire LONGTEXT DEFAULT NULL, variation_id INT NOT NULL, commande_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_61E2C8EB5182BFD8 (variation_id), INDEX IDX_61E2C8EB82EA2E54 (commande_id), INDEX IDX_61E2C8EBB03A8386 (created_by_id), INDEX idx_mouvement_stock_type (type), INDEX idx_mouvement_stock_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE historique_statut_commande ADD CONSTRAINT FK_HISTORIQUE_COMMANDE FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE historique_statut_commande ADD CONSTRAINT FK_HISTORIQUE_CHANGED_BY FOREIGN KEY (changed_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_VARIATION FOREIGN KEY (variation_id) REFERENCES product_variation (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_COMMANDE FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');

        $this->addSql(<<<'SQL'
            UPDATE product_variation variation
            SET variation.stock_reserve = (
                SELECT COALESCE(SUM(ligne.quantite), 0)
                FROM ligne_commande ligne
                INNER JOIN commande commande_stock
                    ON commande_stock.id = ligne.commande_id
                WHERE ligne.variation_id = variation.id
                    AND commande_stock.is_deleted = 0
                    AND commande_stock.statut IN (
                        'EN_ATTENTE_CONFIRMATION',
                        'EN_PREPARATION',
                        'PRETE',
                        'EXPEDIEE',
                        'EN_LIVRAISON'
                    )
            ),
            variation.stock_utilise = (
                SELECT COALESCE(SUM(ligne_livree.quantite), 0)
                FROM ligne_commande ligne_livree
                INNER JOIN commande commande_livree
                    ON commande_livree.id = ligne_livree.commande_id
                WHERE ligne_livree.variation_id = variation.id
                    AND commande_livree.is_deleted = 0
                    AND commande_livree.statut = 'LIVREE'
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE historique_statut_commande DROP FOREIGN KEY FK_HISTORIQUE_COMMANDE');
        $this->addSql('ALTER TABLE historique_statut_commande DROP FOREIGN KEY FK_HISTORIQUE_CHANGED_BY');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_VARIATION');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_COMMANDE');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_CREATED_BY');
        $this->addSql('DROP TABLE historique_statut_commande');
        $this->addSql('DROP TABLE mouvement_stock');
        $this->addSql('ALTER TABLE product_variation DROP stock_reserve');
    }
}
