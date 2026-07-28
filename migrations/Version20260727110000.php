<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les index des commandes et clients avec le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455946703');
        $this->addSql('DROP INDEX IDX_C7440455946703 ON client');
        $this->addSql('CREATE INDEX IDX_C7440455670C757F ON client (fournisseur_id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B19EB6921');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B946703');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67BA76ED395');
        $this->addSql('ALTER TABLE commande CHANGE date date DATETIME NOT NULL');
        $this->addSql('DROP INDEX UNIQ_6EEAA67BF55AE19E ON commande');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DF55AE19E ON commande (numero)');
        $this->addSql('DROP INDEX IDX_6EEAA67BA76ED395 ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67DA76ED395 ON commande (user_id)');
        $this->addSql('DROP INDEX IDX_6EEAA67B946703 ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67D670C757F ON commande (fournisseur_id)');
        $this->addSql('DROP INDEX IDX_6EEAA67B19EB6921 ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B57C53C1');
        $this->addSql('DROP INDEX IDX_3170B74B57C53C1 ON ligne_commande');
        $this->addSql('CREATE INDEX IDX_3170B74B5182BFD8 ON ligne_commande (variation_id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B57C53C1 FOREIGN KEY (variation_id) REFERENCES product_variation (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455946703');
        $this->addSql('DROP INDEX IDX_C7440455670C757F ON client');
        $this->addSql('CREATE INDEX IDX_C7440455946703 ON client (fournisseur_id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B19EB6921');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67B946703');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67BA76ED395');
        $this->addSql("ALTER TABLE commande CHANGE date date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('DROP INDEX UNIQ_6EEAA67DF55AE19E ON commande');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67BF55AE19E ON commande (numero)');
        $this->addSql('DROP INDEX IDX_6EEAA67DA76ED395 ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67BA76ED395 ON commande (user_id)');
        $this->addSql('DROP INDEX IDX_6EEAA67D670C757F ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67B946703 ON commande (fournisseur_id)');
        $this->addSql('DROP INDEX IDX_6EEAA67D19EB6921 ON commande');
        $this->addSql('CREATE INDEX IDX_6EEAA67B19EB6921 ON commande (client_id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67B946703 FOREIGN KEY (fournisseur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B57C53C1');
        $this->addSql('DROP INDEX IDX_3170B74B5182BFD8 ON ligne_commande');
        $this->addSql('CREATE INDEX IDX_3170B74B57C53C1 ON ligne_commande (variation_id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B57C53C1 FOREIGN KEY (variation_id) REFERENCES product_variation (id)');
    }
}
