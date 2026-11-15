<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611170604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, `read` TINYINT NOT NULL, action_url VARCHAR(255) DEFAULT NULL, metadata LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification_preference (id INT AUTO_INCREMENT NOT NULL, enable_in_app TINYINT NOT NULL, enable_email_instant TINYINT NOT NULL, enable_email_digest TINYINT NOT NULL, notify_connection TINYINT NOT NULL, notify_challenge TINYINT NOT NULL, notify_feedback TINYINT NOT NULL, notify_suggestion TINYINT NOT NULL, notify_comment TINYINT NOT NULL, notify_monthly_review TINYINT NOT NULL, preferred_time VARCHAR(10) NOT NULL, notify_habit_reminder TINYINT NOT NULL, notify_goal_deadline TINYINT NOT NULL, notify_weekly_review TINYINT NOT NULL, weekly_review_day INT NOT NULL, digest_frequency VARCHAR(50) NOT NULL, digest_day INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A61B1571A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_preference ADD CONSTRAINT FK_A61B1571A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification_preference DROP FOREIGN KEY FK_A61B1571A76ED395');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE notification_preference');
    }
}
