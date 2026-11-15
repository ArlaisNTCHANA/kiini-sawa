<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611165315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE list_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, subtitle VARCHAR(255) DEFAULT NULL, note LONGTEXT DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, is_completed TINYINT NOT NULL, priority INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, list_id INT NOT NULL, goal_id INT DEFAULT NULL, INDEX IDX_5AD5FAF73DAE168B (list_id), INDEX IDX_5AD5FAF7667D1AFE (goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planner_task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date DATETIME NOT NULL, is_all_day TINYINT NOT NULL, start_time DATETIME DEFAULT NULL, end_time DATETIME DEFAULT NULL, is_completed TINYINT NOT NULL, google_event_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, growth_goal_id INT DEFAULT NULL, INDEX IDX_3CA9573EA76ED395 (user_id), INDEX IDX_3CA9573ED2035C (growth_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_list (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(50) NOT NULL, color VARCHAR(50) NOT NULL, is_default TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_3E49B4D1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE list_item ADD CONSTRAINT FK_5AD5FAF73DAE168B FOREIGN KEY (list_id) REFERENCES user_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE list_item ADD CONSTRAINT FK_5AD5FAF7667D1AFE FOREIGN KEY (goal_id) REFERENCES life_goal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE planner_task ADD CONSTRAINT FK_3CA9573EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planner_task ADD CONSTRAINT FK_3CA9573ED2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_list ADD CONSTRAINT FK_3E49B4D1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE list_item DROP FOREIGN KEY FK_5AD5FAF73DAE168B');
        $this->addSql('ALTER TABLE list_item DROP FOREIGN KEY FK_5AD5FAF7667D1AFE');
        $this->addSql('ALTER TABLE planner_task DROP FOREIGN KEY FK_3CA9573EA76ED395');
        $this->addSql('ALTER TABLE planner_task DROP FOREIGN KEY FK_3CA9573ED2035C');
        $this->addSql('ALTER TABLE user_list DROP FOREIGN KEY FK_3E49B4D1A76ED395');
        $this->addSql('DROP TABLE list_item');
        $this->addSql('DROP TABLE planner_task');
        $this->addSql('DROP TABLE user_list');
    }
}
