<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210203451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE availability (id INT AUTO_INCREMENT NOT NULL, staff_profile_id INT NOT NULL, day VARCHAR(100) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, status TINYINT(1) NOT NULL, INDEX IDX_3FB7A2BF2AA80269 (staff_profile_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE staff_profile_services (staff_profile_id INT NOT NULL, services_id INT NOT NULL, INDEX IDX_FFB1B3EB2AA80269 (staff_profile_id), INDEX IDX_FFB1B3EBAEF5A6C1 (services_id), PRIMARY KEY(staff_profile_id, services_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BF2AA80269 FOREIGN KEY (staff_profile_id) REFERENCES staff_profile (id)');
        $this->addSql('ALTER TABLE staff_profile_services ADD CONSTRAINT FK_FFB1B3EB2AA80269 FOREIGN KEY (staff_profile_id) REFERENCES staff_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE staff_profile_services ADD CONSTRAINT FK_FFB1B3EBAEF5A6C1 FOREIGN KEY (services_id) REFERENCES services (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BF2AA80269');
        $this->addSql('ALTER TABLE staff_profile_services DROP FOREIGN KEY FK_FFB1B3EB2AA80269');
        $this->addSql('ALTER TABLE staff_profile_services DROP FOREIGN KEY FK_FFB1B3EBAEF5A6C1');
        $this->addSql('DROP TABLE availability');
        $this->addSql('DROP TABLE staff_profile_services');
    }
}
