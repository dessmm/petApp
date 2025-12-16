<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210135024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_id_id INT DEFAULT NULL, username VARCHAR(255) NOT NULL, role VARCHAR(100) NOT NULL, action VARCHAR(100) NOT NULL, target_data VARCHAR(255) NOT NULL, ip_address VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_FD06F6479D86650F (user_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6479D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD email VARCHAR(255) NOT NULL, ADD full_name VARCHAR(255) NOT NULL, ADD is_active TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6479D86650F');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('ALTER TABLE user DROP email, DROP full_name, DROP is_active');
    }
}
