<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115173246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations : prix des places';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config ADD org_seat_yearly_price_eur DOUBLE PRECISION DEFAULT 30 NOT NULL, ADD org_seat_yearly_price_xaf INT DEFAULT 10000 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config DROP org_seat_yearly_price_eur, DROP org_seat_yearly_price_xaf');
    }
}
