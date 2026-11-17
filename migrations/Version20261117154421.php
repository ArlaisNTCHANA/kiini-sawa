<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117154421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plan de Transmission : phrase d\'éternité et déplacement de trésor (livre, chapitre 19)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_de_transmission ADD eternity_phrase LONGTEXT DEFAULT NULL, ADD treasure_shift LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_de_transmission DROP eternity_phrase, DROP treasure_shift');
    }
}
