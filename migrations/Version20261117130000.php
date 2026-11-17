<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Actions des rapports de tests ajoutées aux plans (rappel à 30 jours). */
final class Version20261117130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table test_action';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE test_action (id INT AUTO_INCREMENT NOT NULL, text VARCHAR(255) NOT NULL, source VARCHAR(60) NOT NULL, target VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, reminded_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX idx_test_action_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE test_action ADD CONSTRAINT FK_TEST_ACTION_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE test_action');
    }
}
