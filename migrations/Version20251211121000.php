<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update activity_log foreign key to ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        // Drop existing FK and recreate with ON DELETE SET NULL
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6479D86650F');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6479D86650F FOREIGN KEY (user_id_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6479D86650F');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6479D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
    }
}
