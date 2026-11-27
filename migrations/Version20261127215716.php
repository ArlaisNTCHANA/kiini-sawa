<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261127215716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Annonces de l\'équipe aux membres, et préférence « annonces »';
    }

    public function up(Schema $schema): void
    {
        // Annonces de l'équipe (Admin › Écrire aux membres) et préférence « annonces » des membres, 27/11/2026
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, message LONGTEXT NOT NULL, action_label VARCHAR(60) DEFAULT NULL, action_url VARCHAR(255) DEFAULT NULL, audience VARCHAR(20) NOT NULL, organisation_id INT DEFAULT NULL, with_email TINYINT NOT NULL, status VARCHAR(10) NOT NULL, last_user_id INT NOT NULL, reached INT NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_4DB9D91CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification_preference ADD notify_announcements TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91CF675F31B');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('ALTER TABLE notification_preference DROP notify_announcements');
    }
}
