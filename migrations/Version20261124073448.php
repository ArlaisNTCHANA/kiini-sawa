<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124073448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date de naissance : interrupteur du message d\'anniversaire, invitation de l\'accueil écartée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preference ADD notify_birthday TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE user_profile ADD birth_date_prompt_dismissed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preference DROP notify_birthday');
        $this->addSql('ALTER TABLE user_profile DROP birth_date_prompt_dismissed_at');
    }
}
