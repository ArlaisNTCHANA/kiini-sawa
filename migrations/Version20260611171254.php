<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611171254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE telemetry_event (id INT AUTO_INCREMENT NOT NULL, event_type VARCHAR(100) NOT NULL, event_data LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, platform VARCHAR(50) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_E125E78EA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_session (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, last_active_at DATETIME NOT NULL, duration_minutes INT NOT NULL, ip_address VARCHAR(50) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, platform VARCHAR(50) DEFAULT NULL, browser VARCHAR(100) DEFAULT NULL, device VARCHAR(100) DEFAULT NULL, os_version VARCHAR(50) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8849CBDEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE telemetry_event ADD CONSTRAINT FK_E125E78EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE telemetry_event DROP FOREIGN KEY FK_E125E78EA76ED395');
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDEA76ED395');
        $this->addSql('DROP TABLE telemetry_event');
        $this->addSql('DROP TABLE user_session');
    }
}
