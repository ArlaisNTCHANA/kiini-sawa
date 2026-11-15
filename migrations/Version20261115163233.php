<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115163233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lexique des 5 plans : Contribution devient Transmission, Discipline devient Hygiène';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config CHANGE enable_contribution_free enable_transmission_free TINYINT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE principle SET pillar = 'TRANSMISSION' WHERE pillar = 'CONTRIBUTION'");
        $this->addSql("UPDATE principle SET pillar = 'HYGIENE' WHERE pillar = 'DISCIPLINE'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config CHANGE enable_transmission_free enable_contribution_free TINYINT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE principle SET pillar = 'CONTRIBUTION' WHERE pillar = 'TRANSMISSION'");
        $this->addSql("UPDATE principle SET pillar = 'DISCIPLINE' WHERE pillar = 'HYGIENE'");
    }
}
