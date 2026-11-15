<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115165737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4 : activité, préférence partenaire inactif, revue mensuelle unique par mois';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_monthly_review_user_month ON monthly_review (user_id, month)');
        $this->addSql('ALTER TABLE notification_preference ADD notify_partner_inactive TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE user ADD last_active_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_monthly_review_user_month ON monthly_review');
        $this->addSql('ALTER TABLE notification_preference DROP notify_partner_inactive');
        $this->addSql('ALTER TABLE `user` DROP last_active_at');
    }
}
