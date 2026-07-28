<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réserve le stock des commandes en attente de confirmation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE product_variation variation
            SET variation.stock_utilise = (
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
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE product_variation variation
            SET variation.stock_utilise = (
                SELECT COALESCE(SUM(ligne.quantite), 0)
                FROM ligne_commande ligne
                INNER JOIN commande commande_stock
                    ON commande_stock.id = ligne.commande_id
                WHERE ligne.variation_id = variation.id
                    AND commande_stock.is_deleted = 0
                    AND commande_stock.statut IN (
                        'EN_PREPARATION',
                        'PRETE',
                        'EXPEDIEE',
                        'EN_LIVRAISON'
                    )
            )
        SQL);
    }
}
