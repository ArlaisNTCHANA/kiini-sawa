<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Redevabilité : alertes automatiques (seuils, constat, reprise à 72 h) et date de mise à jour des objectifs';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accountability_commitment ADD alert_habit_days INT DEFAULT NULL, ADD alert_goal_days INT DEFAULT NULL, ADD alert_inactive_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accountability_nudge ADD fact VARCHAR(255) DEFAULT NULL, ADD resumed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE life_goal ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accountability_commitment DROP alert_habit_days, DROP alert_goal_days, DROP alert_inactive_days');
        $this->addSql('ALTER TABLE accountability_nudge DROP fact, DROP resumed_at');
        $this->addSql('ALTER TABLE life_goal DROP updated_at');
    }
}
