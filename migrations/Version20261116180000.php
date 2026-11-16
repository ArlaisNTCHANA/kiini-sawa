<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Provenance des inscrits (module Media).
 */
final class Version20261116180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table signup_origin : provenance des inscrits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE signup_origin (id INT AUTO_INCREMENT NOT NULL, `source` VARCHAR(60) NOT NULL, medium VARCHAR(60) DEFAULT NULL, campaign VARCHAR(120) DEFAULT NULL, `content` VARCHAR(120) DEFAULT NULL, referrer VARCHAR(190) DEFAULT NULL, landing_path VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A7BACEC0A76ED395 (user_id), INDEX idx_signup_origin_source (`source`), INDEX idx_signup_origin_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE signup_origin ADD CONSTRAINT FK_A7BACEC0A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE signup_origin');
    }
}
