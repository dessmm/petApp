<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210193233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE staff_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, services JSON DEFAULT NULL, availability JSON DEFAULT NULL, UNIQUE INDEX UNIQ_DDE1BDB9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE staff_profile ADD CONSTRAINT FK_DDE1BDB9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE appointment ADD staff_id INT NOT NULL');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844D4D57CD FOREIGN KEY (staff_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_FE38F844D4D57CD ON appointment (staff_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE staff_profile DROP FOREIGN KEY FK_DDE1BDB9A76ED395');
        $this->addSql('DROP TABLE staff_profile');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844D4D57CD');
        $this->addSql('DROP INDEX IDX_FE38F844D4D57CD ON appointment');
        $this->addSql('ALTER TABLE appointment DROP staff_id');
    }
}
