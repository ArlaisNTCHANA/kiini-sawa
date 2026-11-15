<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611170018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE challenge (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, habit_title VARCHAR(255) DEFAULT NULL, target_value INT DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, frequency VARCHAR(50) NOT NULL, visibility VARCHAR(50) NOT NULL, recurrence_day INT DEFAULT NULL, recurrence_days VARCHAR(255) DEFAULT NULL, recurrence_week INT DEFAULT NULL, recurrence_ordinal INT DEFAULT NULL, created_at DATETIME NOT NULL, creator_id INT NOT NULL, INDEX IDX_D709895161220EA6 (creator_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_activity (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, challenge_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_314AEEBA98A21AC6 (challenge_id), INDEX IDX_314AEEBAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_invitation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, challenge_id INT NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_910D78AB98A21AC6 (challenge_id), INDEX IDX_910D78ABF624B39D (sender_id), INDEX IDX_910D78ABCD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_log (id INT AUTO_INCREMENT NOT NULL, date DATETIME NOT NULL, status TINYINT NOT NULL, participant_id INT NOT NULL, INDEX IDX_9674C5C99D1C3019 (participant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_participant (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, progress INT NOT NULL, challenge_id INT NOT NULL, user_id INT NOT NULL, linked_goal_id INT DEFAULT NULL, linked_growth_goal_id INT DEFAULT NULL, INDEX IDX_AE23C3AC98A21AC6 (challenge_id), INDEX IDX_AE23C3ACA76ED395 (user_id), INDEX IDX_AE23C3ACD352269 (linked_goal_id), INDEX IDX_AE23C3AC4630ADA4 (linked_growth_goal_id), UNIQUE INDEX UNIQ_CHALLENGE_USER (challenge_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE challenge ADD CONSTRAINT FK_D709895161220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_activity ADD CONSTRAINT FK_314AEEBA98A21AC6 FOREIGN KEY (challenge_id) REFERENCES challenge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_activity ADD CONSTRAINT FK_314AEEBAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_invitation ADD CONSTRAINT FK_910D78AB98A21AC6 FOREIGN KEY (challenge_id) REFERENCES challenge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_invitation ADD CONSTRAINT FK_910D78ABF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_invitation ADD CONSTRAINT FK_910D78ABCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_log ADD CONSTRAINT FK_9674C5C99D1C3019 FOREIGN KEY (participant_id) REFERENCES challenge_participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_participant ADD CONSTRAINT FK_AE23C3AC98A21AC6 FOREIGN KEY (challenge_id) REFERENCES challenge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_participant ADD CONSTRAINT FK_AE23C3ACA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE challenge_participant ADD CONSTRAINT FK_AE23C3ACD352269 FOREIGN KEY (linked_goal_id) REFERENCES life_goal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE challenge_participant ADD CONSTRAINT FK_AE23C3AC4630ADA4 FOREIGN KEY (linked_growth_goal_id) REFERENCES growth_goal (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE challenge DROP FOREIGN KEY FK_D709895161220EA6');
        $this->addSql('ALTER TABLE challenge_activity DROP FOREIGN KEY FK_314AEEBA98A21AC6');
        $this->addSql('ALTER TABLE challenge_activity DROP FOREIGN KEY FK_314AEEBAA76ED395');
        $this->addSql('ALTER TABLE challenge_invitation DROP FOREIGN KEY FK_910D78AB98A21AC6');
        $this->addSql('ALTER TABLE challenge_invitation DROP FOREIGN KEY FK_910D78ABF624B39D');
        $this->addSql('ALTER TABLE challenge_invitation DROP FOREIGN KEY FK_910D78ABCD53EDB6');
        $this->addSql('ALTER TABLE challenge_log DROP FOREIGN KEY FK_9674C5C99D1C3019');
        $this->addSql('ALTER TABLE challenge_participant DROP FOREIGN KEY FK_AE23C3AC98A21AC6');
        $this->addSql('ALTER TABLE challenge_participant DROP FOREIGN KEY FK_AE23C3ACA76ED395');
        $this->addSql('ALTER TABLE challenge_participant DROP FOREIGN KEY FK_AE23C3ACD352269');
        $this->addSql('ALTER TABLE challenge_participant DROP FOREIGN KEY FK_AE23C3AC4630ADA4');
        $this->addSql('DROP TABLE challenge');
        $this->addSql('DROP TABLE challenge_activity');
        $this->addSql('DROP TABLE challenge_invitation');
        $this->addSql('DROP TABLE challenge_log');
        $this->addSql('DROP TABLE challenge_participant');
    }
}
