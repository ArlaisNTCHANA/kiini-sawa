<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117151426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Livre : texte intégral réservé à PRO, l\'essentiel pour les comptes gratuits (décision du fondateur, 17/11/2026)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config CHANGE enable_method_free enable_method_free TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE app_config SET enable_method_free = 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config CHANGE enable_method_free enable_method_free TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE app_config SET enable_method_free = 1');
    }
}
