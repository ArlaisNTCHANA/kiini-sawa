<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115220441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offre Duo : prix';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config ADD duo_yearly_price_eur DOUBLE PRECISION DEFAULT 119.99 NOT NULL, ADD duo_yearly_price_xaf INT DEFAULT 39000 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_config DROP duo_yearly_price_eur, DROP duo_yearly_price_xaf');
    }
}
