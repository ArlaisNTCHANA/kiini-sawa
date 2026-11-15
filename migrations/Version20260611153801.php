<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611153801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_log (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, sleep_hours DOUBLE PRECISION DEFAULT NULL, sleep_quality INT DEFAULT NULL, energy_level INT DEFAULT NULL, stress_level INT DEFAULT NULL, intention VARCHAR(255) DEFAULT NULL, mood VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_dhygiene_id INT NOT NULL, INDEX IDX_8D0D8EA9CEC05175 (plan_dhygiene_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hygiene_domain (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, target_score INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_dhygiene_id INT NOT NULL, INDEX IDX_DC9FAEA5CEC05175 (plan_dhygiene_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE daily_log ADD CONSTRAINT FK_8D0D8EA9CEC05175 FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id)');
        $this->addSql('ALTER TABLE hygiene_domain ADD CONSTRAINT FK_DC9FAEA5CEC05175 FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id)');
        $this->addSql('ALTER TABLE comment ADD hygiene_domain_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE9C645D5 FOREIGN KEY (hygiene_domain_id) REFERENCES hygiene_domain (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526CE9C645D5 ON comment (hygiene_domain_id)');
        $this->addSql('ALTER TABLE habit ADD hygiene_domain_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172E9C645D5 FOREIGN KEY (hygiene_domain_id) REFERENCES hygiene_domain (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_44FE2172E9C645D5 ON habit (hygiene_domain_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_log DROP FOREIGN KEY FK_8D0D8EA9CEC05175');
        $this->addSql('ALTER TABLE hygiene_domain DROP FOREIGN KEY FK_DC9FAEA5CEC05175');
        $this->addSql('DROP TABLE daily_log');
        $this->addSql('DROP TABLE hygiene_domain');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE9C645D5');
        $this->addSql('DROP INDEX IDX_9474526CE9C645D5 ON comment');
        $this->addSql('ALTER TABLE comment DROP hygiene_domain_id');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172E9C645D5');
        $this->addSql('DROP INDEX IDX_44FE2172E9C645D5 ON habit');
        $this->addSql('ALTER TABLE habit DROP hygiene_domain_id');
    }
}
