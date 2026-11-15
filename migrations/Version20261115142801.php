<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115142801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression du mot de passe démo stocké en clair (app_config.demo_password)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config DROP demo_password');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config ADD demo_password VARCHAR(255) DEFAULT \'password123\' NOT NULL');
    }
}
