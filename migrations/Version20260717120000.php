<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store product image keys without the public uploads/products prefix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE product SET image = SUBSTRING(image, 18) WHERE image LIKE 'uploads/products/%'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE product SET image = CONCAT('uploads/products/', image) WHERE image IS NOT NULL AND image NOT LIKE 'uploads/products/%'"
        );
    }
}
