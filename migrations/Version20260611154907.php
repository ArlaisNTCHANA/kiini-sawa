<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611154907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personality_answer (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, value VARCHAR(255) NOT NULL, question_id INT NOT NULL, INDEX IDX_2C9A4A811E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personality_profile (id INT AUTO_INCREMENT NOT NULL, test_type VARCHAR(50) NOT NULL, code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, strengths LONGTEXT DEFAULT NULL, weaknesses LONGTEXT DEFAULT NULL, relationships LONGTEXT DEFAULT NULL, career LONGTEXT DEFAULT NULL, emotional_dynamics LONGTEXT DEFAULT NULL, essence LONGTEXT DEFAULT NULL, growth_axes LONGTEXT DEFAULT NULL, feedback_low LONGTEXT DEFAULT NULL, feedback_mid LONGTEXT DEFAULT NULL, feedback_high LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_TEST_TYPE_CODE (test_type, code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personality_question (id INT AUTO_INCREMENT NOT NULL, test_type VARCHAR(50) NOT NULL, category VARCHAR(100) DEFAULT NULL, question LONGTEXT NOT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profile_test (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, result LONGTEXT NOT NULL, insights LONGTEXT DEFAULT NULL, attachments LONGTEXT DEFAULT NULL, date DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_6EB7F198A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE personality_answer ADD CONSTRAINT FK_2C9A4A811E27F6BF FOREIGN KEY (question_id) REFERENCES personality_question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_test ADD CONSTRAINT FK_6EB7F198A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personality_answer DROP FOREIGN KEY FK_2C9A4A811E27F6BF');
        $this->addSql('ALTER TABLE profile_test DROP FOREIGN KEY FK_6EB7F198A76ED395');
        $this->addSql('DROP TABLE personality_answer');
        $this->addSql('DROP TABLE personality_profile');
        $this->addSql('DROP TABLE personality_question');
        $this->addSql('DROP TABLE profile_test');
    }
}
