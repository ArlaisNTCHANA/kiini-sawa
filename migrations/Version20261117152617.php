<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117152617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Livre : saisons (nature, revue de saison) et bilan du jour (intention tenue, humeur, gratitude)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_log ADD intention_kept TINYINT DEFAULT NULL, ADD mood_level SMALLINT DEFAULT NULL, ADD gratitude VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE season ADD kind VARCHAR(12) DEFAULT NULL, ADD review_note LONGTEXT DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_log DROP intention_kept, DROP mood_level, DROP gratitude');
        $this->addSql('ALTER TABLE season DROP kind, DROP review_note, DROP reviewed_at');
    }
}
