<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115171230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5 : prix en FCFA, consignes de paiement manuel, suivi des paiements ; offre gratuite par défaut';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config ADD pro_monthly_price_xaf INT DEFAULT 3000 NOT NULL, ADD pro_yearly_price_xaf INT DEFAULT 25000 NOT NULL, ADD enable_development_free TINYINT DEFAULT 0 NOT NULL, ADD bank_transfer_instructions LONGTEXT DEFAULT NULL, ADD mobile_money_instructions LONGTEXT DEFAULT NULL, ADD payment_notification_email VARCHAR(180) DEFAULT NULL');
        // Offre gratuite : la pratique quotidienne et l'analyse du Plan de Vie sont gratuites ;
        // les 4 plans stratégiques, la carte, le PDF, la revue mensuelle et l'historique sont PRO.
        $this->addSql('UPDATE app_config SET enable_journal_free = 1, enable_habits_free = 1, enable_planner_free = 1, enable_coherence_analysis_free = 1, enable_social_free = 1, enable_discovery_free = 1, enable_method_free = 1');
        $this->addSql('ALTER TABLE transaction ADD method VARCHAR(30) DEFAULT \'BANK_TRANSFER\' NOT NULL, ADD period VARCHAR(10) DEFAULT \'MONTHLY\' NOT NULL, ADD reference VARCHAR(255) DEFAULT NULL, ADD paid_at DATE DEFAULT NULL, ADD receipt_number VARCHAR(30) DEFAULT NULL, ADD processed_at DATETIME DEFAULT NULL, ADD admin_note LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_723705D1B0ADB74C ON transaction (receipt_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config DROP pro_monthly_price_xaf, DROP pro_yearly_price_xaf, DROP enable_development_free, DROP bank_transfer_instructions, DROP mobile_money_instructions, DROP payment_notification_email');
        $this->addSql('DROP INDEX UNIQ_723705D1B0ADB74C ON transaction');
        $this->addSql('ALTER TABLE transaction DROP method, DROP period, DROP reference, DROP paid_at, DROP receipt_number, DROP processed_at, DROP admin_note');
    }
}
