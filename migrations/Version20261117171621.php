<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117171621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entonnoir : compteur de visites de la vitrine par jour';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_visit (day DATE NOT NULL, visits INT DEFAULT 0 NOT NULL, PRIMARY KEY (day)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE daily_visit');
    }
}
