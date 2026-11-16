<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Préférence « conseils de démarrage » (emails d'accueil). */
final class Version20261116200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'notification_preference.notify_onboarding';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preference ADD notify_onboarding TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preference DROP notify_onboarding');
    }
}
