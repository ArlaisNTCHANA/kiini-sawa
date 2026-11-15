<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611152818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE confirm_point (id INT AUTO_INCREMENT NOT NULL, statement LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, plan_de_croissance_id INT NOT NULL, INDEX IDX_405BF84E9C828A73 (plan_de_croissance_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evidence (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, content LONGTEXT NOT NULL, source_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, confirm_point_id INT NOT NULL, INDEX IDX_C6157103C998673 (confirm_point_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE growth_action (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, deadline DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, growth_goal_id INT NOT NULL, INDEX IDX_EEEEE448D2035C (growth_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE growth_goal (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, axis VARCHAR(50) NOT NULL, character_action VARCHAR(50) DEFAULT NULL, strategy LONGTEXT NOT NULL, metric VARCHAR(255) NOT NULL, progress INT NOT NULL, why LONGTEXT DEFAULT NULL, deadline DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_de_croissance_id INT NOT NULL, INDEX IDX_5EC0A4299C828A73 (plan_de_croissance_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE growth_goal_life_goal (growth_goal_id INT NOT NULL, life_goal_id INT NOT NULL, INDEX IDX_AAED9AAD2035C (growth_goal_id), INDEX IDX_AAED9AAC00EDB6D (life_goal_id), PRIMARY KEY (growth_goal_id, life_goal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE growth_goal_score (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, progress_value INT NOT NULL, note LONGTEXT DEFAULT NULL, date DATETIME NOT NULL, growth_goal_id INT NOT NULL, INDEX IDX_DCA5BD9D2035C (growth_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE confirm_point ADD CONSTRAINT FK_405BF84E9C828A73 FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id)');
        $this->addSql('ALTER TABLE evidence ADD CONSTRAINT FK_C6157103C998673 FOREIGN KEY (confirm_point_id) REFERENCES confirm_point (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE growth_action ADD CONSTRAINT FK_EEEEE448D2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE growth_goal ADD CONSTRAINT FK_5EC0A4299C828A73 FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id)');
        $this->addSql('ALTER TABLE growth_goal_life_goal ADD CONSTRAINT FK_AAED9AAD2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE growth_goal_life_goal ADD CONSTRAINT FK_AAED9AAC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE growth_goal_score ADD CONSTRAINT FK_DCA5BD9D2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD growth_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CD2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526CD2035C ON comment (growth_goal_id)');
        $this->addSql('ALTER TABLE habit ADD growth_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172D2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_44FE2172D2035C ON habit (growth_goal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE confirm_point DROP FOREIGN KEY FK_405BF84E9C828A73');
        $this->addSql('ALTER TABLE evidence DROP FOREIGN KEY FK_C6157103C998673');
        $this->addSql('ALTER TABLE growth_action DROP FOREIGN KEY FK_EEEEE448D2035C');
        $this->addSql('ALTER TABLE growth_goal DROP FOREIGN KEY FK_5EC0A4299C828A73');
        $this->addSql('ALTER TABLE growth_goal_life_goal DROP FOREIGN KEY FK_AAED9AAD2035C');
        $this->addSql('ALTER TABLE growth_goal_life_goal DROP FOREIGN KEY FK_AAED9AAC00EDB6D');
        $this->addSql('ALTER TABLE growth_goal_score DROP FOREIGN KEY FK_DCA5BD9D2035C');
        $this->addSql('DROP TABLE confirm_point');
        $this->addSql('DROP TABLE evidence');
        $this->addSql('DROP TABLE growth_action');
        $this->addSql('DROP TABLE growth_goal');
        $this->addSql('DROP TABLE growth_goal_life_goal');
        $this->addSql('DROP TABLE growth_goal_score');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CD2035C');
        $this->addSql('DROP INDEX IDX_9474526CD2035C ON comment');
        $this->addSql('ALTER TABLE comment DROP growth_goal_id');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172D2035C');
        $this->addSql('DROP INDEX IDX_44FE2172D2035C ON habit');
        $this->addSql('ALTER TABLE habit DROP growth_goal_id');
    }
}
