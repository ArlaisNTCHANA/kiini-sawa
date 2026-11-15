<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611171007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_feedback (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, category VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_DAA924F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_feedback (id INT AUTO_INCREMENT NOT NULL, answers LONGTEXT DEFAULT NULL, relationship VARCHAR(255) DEFAULT NULL, is_anonymous TINYINT NOT NULL, respondent_name VARCHAR(255) DEFAULT NULL, strengths LONGTEXT DEFAULT NULL, improvements LONGTEXT DEFAULT NULL, qualities LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, feedback_request_id INT NOT NULL, INDEX IDX_206F817FA76ED395 (user_id), INDEX IDX_206F817F1E7396FF (feedback_request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE feedback (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, impact VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, from_user_id INT NOT NULL, to_user_id INT NOT NULL, INDEX IDX_D22944582130303A (from_user_id), INDEX IDX_D229445829F6EE60 (to_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE feedback_question (id INT AUTO_INCREMENT NOT NULL, label_fr VARCHAR(255) NOT NULL, label_en VARCHAR(255) NOT NULL, placeholder_fr VARCHAR(255) DEFAULT NULL, placeholder_en VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, options LONGTEXT DEFAULT NULL, sort_order INT NOT NULL, is_active TINYINT NOT NULL, is_mandatory TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE feedback_request (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, force_real_name TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_AACEAF3C5F37A13B (token), INDEX IDX_AACEAF3CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_feedback ADD CONSTRAINT FK_DAA924F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_feedback ADD CONSTRAINT FK_206F817FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_feedback ADD CONSTRAINT FK_206F817F1E7396FF FOREIGN KEY (feedback_request_id) REFERENCES feedback_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D22944582130303A FOREIGN KEY (from_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D229445829F6EE60 FOREIGN KEY (to_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback_request ADD CONSTRAINT FK_AACEAF3CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_feedback DROP FOREIGN KEY FK_DAA924F5A76ED395');
        $this->addSql('ALTER TABLE community_feedback DROP FOREIGN KEY FK_206F817FA76ED395');
        $this->addSql('ALTER TABLE community_feedback DROP FOREIGN KEY FK_206F817F1E7396FF');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D22944582130303A');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D229445829F6EE60');
        $this->addSql('ALTER TABLE feedback_request DROP FOREIGN KEY FK_AACEAF3CA76ED395');
        $this->addSql('DROP TABLE app_feedback');
        $this->addSql('DROP TABLE community_feedback');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE feedback_question');
        $this->addSql('DROP TABLE feedback_request');
    }
}
