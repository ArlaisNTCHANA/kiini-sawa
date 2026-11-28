<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261128064442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des actions d\'administration';
    }

    public function up(Schema $schema): void
    {
        // Admin › Réglages › Administrateurs et journal (28/11/2026)
        $this->addSql('CREATE TABLE admin_action (id INT AUTO_INCREMENT NOT NULL, actor_email VARCHAR(180) NOT NULL, route VARCHAR(80) NOT NULL, message VARCHAR(255) NOT NULL, target VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_1A04BD6B10DAF24A (actor_id), INDEX idx_admin_action_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_action ADD CONSTRAINT FK_1A04BD6B10DAF24A FOREIGN KEY (actor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_action DROP FOREIGN KEY FK_1A04BD6B10DAF24A');
        $this->addSql('DROP TABLE admin_action');
    }
}
