<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124145228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages « Saviez-vous que… » : préférence du membre, messages désactivés par l\'administrateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_config ADD disabled_tips VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE notification_preference ADD notify_tips TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_config DROP disabled_tips');
        $this->addSql('ALTER TABLE notification_preference DROP notify_tips');
    }
}
