<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611153437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE career_step (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, company VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, deadline DATETIME DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_de_developpement_personnel_id INT NOT NULL, INDEX IDX_9D10DDA546D52AA2 (plan_de_developpement_personnel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE learning_resource (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, url VARCHAR(255) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, progress INT NOT NULL, rating INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, skill_goal_id INT NOT NULL, INDEX IDX_98E1F0087C8884D7 (skill_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill_goal (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, target_level VARCHAR(255) DEFAULT NULL, deadline DATETIME DEFAULT NULL, progress INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_de_developpement_personnel_id INT NOT NULL, INDEX IDX_BB9D779C46D52AA2 (plan_de_developpement_personnel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill_goal_life_goal (skill_goal_id INT NOT NULL, life_goal_id INT NOT NULL, INDEX IDX_D0408B8C7C8884D7 (skill_goal_id), INDEX IDX_D0408B8CC00EDB6D (life_goal_id), PRIMARY KEY (skill_goal_id, life_goal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE career_step ADD CONSTRAINT FK_9D10DDA546D52AA2 FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id)');
        $this->addSql('ALTER TABLE learning_resource ADD CONSTRAINT FK_98E1F0087C8884D7 FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id)');
        $this->addSql('ALTER TABLE skill_goal ADD CONSTRAINT FK_BB9D779C46D52AA2 FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id)');
        $this->addSql('ALTER TABLE skill_goal_life_goal ADD CONSTRAINT FK_D0408B8C7C8884D7 FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE skill_goal_life_goal ADD CONSTRAINT FK_D0408B8CC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD skill_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7C8884D7 FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526C7C8884D7 ON comment (skill_goal_id)');
        $this->addSql('ALTER TABLE habit ADD skill_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE21727C8884D7 FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_44FE21727C8884D7 ON habit (skill_goal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE career_step DROP FOREIGN KEY FK_9D10DDA546D52AA2');
        $this->addSql('ALTER TABLE learning_resource DROP FOREIGN KEY FK_98E1F0087C8884D7');
        $this->addSql('ALTER TABLE skill_goal DROP FOREIGN KEY FK_BB9D779C46D52AA2');
        $this->addSql('ALTER TABLE skill_goal_life_goal DROP FOREIGN KEY FK_D0408B8C7C8884D7');
        $this->addSql('ALTER TABLE skill_goal_life_goal DROP FOREIGN KEY FK_D0408B8CC00EDB6D');
        $this->addSql('DROP TABLE career_step');
        $this->addSql('DROP TABLE learning_resource');
        $this->addSql('DROP TABLE skill_goal');
        $this->addSql('DROP TABLE skill_goal_life_goal');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7C8884D7');
        $this->addSql('DROP INDEX IDX_9474526C7C8884D7 ON comment');
        $this->addSql('ALTER TABLE comment DROP skill_goal_id');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE21727C8884D7');
        $this->addSql('DROP INDEX IDX_44FE21727C8884D7 ON habit');
        $this->addSql('ALTER TABLE habit DROP skill_goal_id');
    }
}
