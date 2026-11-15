<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611154052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE legacy_goal (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, target_beneficiary VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, progress INT NOT NULL, deadline DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_de_transmission_id INT NOT NULL, INDEX IDX_D36C5036868E2FF1 (plan_de_transmission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE talent_don (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_de_transmission_id INT NOT NULL, INDEX IDX_A38961DF868E2FF1 (plan_de_transmission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transmission_action (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, deadline DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, legacy_goal_id INT NOT NULL, INDEX IDX_B33CE2DFB8182CE6 (legacy_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE legacy_goal ADD CONSTRAINT FK_D36C5036868E2FF1 FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id)');
        $this->addSql('ALTER TABLE talent_don ADD CONSTRAINT FK_A38961DF868E2FF1 FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id)');
        $this->addSql('ALTER TABLE transmission_action ADD CONSTRAINT FK_B33CE2DFB8182CE6 FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id)');
        $this->addSql('ALTER TABLE comment ADD legacy_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CB8182CE6 FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526CB8182CE6 ON comment (legacy_goal_id)');
        $this->addSql('ALTER TABLE habit ADD legacy_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172B8182CE6 FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_44FE2172B8182CE6 ON habit (legacy_goal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE legacy_goal DROP FOREIGN KEY FK_D36C5036868E2FF1');
        $this->addSql('ALTER TABLE talent_don DROP FOREIGN KEY FK_A38961DF868E2FF1');
        $this->addSql('ALTER TABLE transmission_action DROP FOREIGN KEY FK_B33CE2DFB8182CE6');
        $this->addSql('DROP TABLE legacy_goal');
        $this->addSql('DROP TABLE talent_don');
        $this->addSql('DROP TABLE transmission_action');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CB8182CE6');
        $this->addSql('DROP INDEX IDX_9474526CB8182CE6 ON comment');
        $this->addSql('ALTER TABLE comment DROP legacy_goal_id');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172B8182CE6');
        $this->addSql('DROP INDEX IDX_44FE2172B8182CE6 ON habit');
        $this->addSql('ALTER TABLE habit DROP legacy_goal_id');
    }
}
