<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Alignement strict des tarifs FCFA (XAF) sur la valeur réelle en Euro (taux officiel 1 € = 655,957 XAF).
 */
final class Version20261119183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alignement des tarifs FCFA sur la parité réelle Euro dans app_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE app_config SET pro_monthly_price_xaf = 6500, pro_yearly_price_xaf = 52500, duo_yearly_price_xaf = 78500, org_seat_yearly_price_xaf = 20000 WHERE id = 'default'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE app_config SET pro_monthly_price_xaf = 3000, pro_yearly_price_xaf = 25000, duo_yearly_price_xaf = 39000, org_seat_yearly_price_xaf = 10000 WHERE id = 'default'");
    }
}
