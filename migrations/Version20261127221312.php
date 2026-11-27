<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261127221312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compteurs anonymes de la Boussole 360 (visiteurs, passations gardées)';
    }

    public function up(Schema $schema): void
    {
        // Admin › Les chiffres › La Boussole 360 (27/11/2026)
        $this->addSql('CREATE TABLE boussole_daily (day DATE NOT NULL, visitors INT DEFAULT 0 NOT NULL, new_members INT DEFAULT 0 NOT NULL, returning_members INT DEFAULT 0 NOT NULL, PRIMARY KEY (day)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE boussole_daily');
    }
}
