<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611151841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, section VARCHAR(100) DEFAULT NULL, author_id INT NOT NULL, purpose_id INT DEFAULT NULL, vision_id INT DEFAULT NULL, life_goal_id INT DEFAULT NULL, habit_id INT DEFAULT NULL, target_user_id INT DEFAULT NULL, INDEX IDX_9474526CF675F31B (author_id), INDEX IDX_9474526C7FC21131 (purpose_id), INDEX IDX_9474526C1F4FDCB (vision_id), INDEX IDX_9474526CC00EDB6D (life_goal_id), INDEX IDX_9474526CE7AEB3B2 (habit_id), INDEX IDX_9474526C6C066AFE (target_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE habit (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, frequency VARCHAR(50) NOT NULL, end_date DATE DEFAULT NULL, recurrence_day INT DEFAULT NULL, recurrence_week INT DEFAULT NULL, implementation_when LONGTEXT DEFAULT NULL, implementation_then LONGTEXT DEFAULT NULL, support_strategy LONGTEXT DEFAULT NULL, rooting_intention LONGTEXT DEFAULT NULL, rooting_action LONGTEXT DEFAULT NULL, rooting_moment LONGTEXT DEFAULT NULL, rooting_anchor LONGTEXT DEFAULT NULL, rooting_benefit LONGTEXT DEFAULT NULL, rooting_facilitator LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, life_goal_id INT DEFAULT NULL, INDEX IDX_44FE2172A76ED395 (user_id), INDEX IDX_44FE2172C00EDB6D (life_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE habit_log (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, status TINYINT NOT NULL, created_at DATETIME NOT NULL, habit_id INT NOT NULL, INDEX IDX_C1637C45E7AEB3B2 (habit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE season (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, theme VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_F0E45BA9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_profile (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, marital_status VARCHAR(50) DEFAULT NULL, children INT NOT NULL, phone VARCHAR(50) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, profession VARCHAR(150) DEFAULT NULL, social_linked_in VARCHAR(255) DEFAULT NULL, social_twitter VARCHAR(255) DEFAULT NULL, social_website VARCHAR(255) DEFAULT NULL, social_facebook VARCHAR(255) DEFAULT NULL, whatsapp VARCHAR(50) DEFAULT NULL, onboarding_completed TINYINT NOT NULL, allow_team_advice TINYINT NOT NULL, timezone VARCHAR(100) NOT NULL, locale VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_D95AB405A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7FC21131 FOREIGN KEY (purpose_id) REFERENCES purpose (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C1F4FDCB FOREIGN KEY (vision_id) REFERENCES vision (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE7AEB3B2 FOREIGN KEY (habit_id) REFERENCES habit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C6C066AFE FOREIGN KEY (target_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172C00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE habit_log ADD CONSTRAINT FK_C1637C45E7AEB3B2 FOREIGN KEY (habit_id) REFERENCES habit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season ADD CONSTRAINT FK_F0E45BA9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT FK_D95AB405A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7FC21131');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C1F4FDCB');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CC00EDB6D');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE7AEB3B2');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C6C066AFE');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172A76ED395');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172C00EDB6D');
        $this->addSql('ALTER TABLE habit_log DROP FOREIGN KEY FK_C1637C45E7AEB3B2');
        $this->addSql('ALTER TABLE season DROP FOREIGN KEY FK_F0E45BA9A76ED395');
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY FK_D95AB405A76ED395');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE habit');
        $this->addSql('DROP TABLE habit_log');
        $this->addSql('DROP TABLE season');
        $this->addSql('DROP TABLE user_profile');
    }
}
