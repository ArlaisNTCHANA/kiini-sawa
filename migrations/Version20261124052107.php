<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Académie, Lot 3 : rythme de suivi par e-mail sur chaque inscription (libre, quotidien, 3 par semaine), pause
 * automatique et anti-doublon du jour.
 */
final class Version20261124052107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Académie : rythme de suivi par e-mail des inscriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE academy_enrollment ADD pace_mode VARCHAR(20) DEFAULT \'FREE\' NOT NULL, ADD pace_paused TINYINT DEFAULT 0 NOT NULL, ADD emails_without_progress INT DEFAULT 0 NOT NULL, ADD last_email_sent_on DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE academy_enrollment DROP pace_mode, DROP pace_paused, DROP emails_without_progress, DROP last_email_sent_on');
    }
}
