<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rattrapage du schéma (audit du 23/11/2026, point T2) : `doctrine:schema:validate` échouait.
 * - Valeurs par défaut des colonnes de prix FCFA alignées sur l'entité AppConfig (les prix enregistrés ne changent pas :
 *   ils restent réglés dans Admin › Configuration).
 * - Index des tables du couple renommés comme Doctrine les nomme.
 */
final class Version20261123080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattrapage du schéma : valeurs par défaut des prix FCFA et noms d\'index des tables du couple';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_config CHANGE pro_monthly_price_xaf pro_monthly_price_xaf INT DEFAULT 6500 NOT NULL, CHANGE pro_yearly_price_xaf pro_yearly_price_xaf INT DEFAULT 52500 NOT NULL, CHANGE org_seat_yearly_price_xaf org_seat_yearly_price_xaf INT DEFAULT 20000 NOT NULL, CHANGE duo_yearly_price_xaf duo_yearly_price_xaf INT DEFAULT 78500 NOT NULL');
        $this->addSql('ALTER TABLE couple_challenge_day RENAME INDEX idx_couple_challenge_day_challenge TO IDX_8397092698A21AC6');
        $this->addSql('ALTER TABLE couple_challenge RENAME INDEX idx_couple_challenge_couple TO IDX_4B780E3BF66468CA');
        $this->addSql('ALTER TABLE couple_meditation RENAME INDEX idx_couple_meditation_couple TO IDX_E9C69092F66468CA');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE couple_meditation RENAME INDEX IDX_E9C69092F66468CA TO idx_couple_meditation_couple');
        $this->addSql('ALTER TABLE couple_challenge RENAME INDEX IDX_4B780E3BF66468CA TO idx_couple_challenge_couple');
        $this->addSql('ALTER TABLE couple_challenge_day RENAME INDEX IDX_8397092698A21AC6 TO idx_couple_challenge_day_challenge');
        $this->addSql('ALTER TABLE app_config CHANGE pro_monthly_price_xaf pro_monthly_price_xaf INT DEFAULT 3000 NOT NULL, CHANGE pro_yearly_price_xaf pro_yearly_price_xaf INT DEFAULT 25000 NOT NULL, CHANGE org_seat_yearly_price_xaf org_seat_yearly_price_xaf INT DEFAULT 10000 NOT NULL, CHANGE duo_yearly_price_xaf duo_yearly_price_xaf INT DEFAULT 39000 NOT NULL');
    }
}
