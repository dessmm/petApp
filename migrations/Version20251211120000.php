<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived_at column to user table';
    }

    public function up(Schema $schema): void
    {
        // add archived_at (nullable) to user table
        $this->addSql('ALTER TABLE user ADD archived_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP archived_at');
    }
}
