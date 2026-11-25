<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261125061749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Académie : vignette des parcours (image ou mot) et plans ajoutés à la main';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE academy_course ADD cover_image VARCHAR(16) DEFAULT NULL, ADD cover_word VARCHAR(40) DEFAULT NULL, ADD extra_plans JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE academy_course DROP cover_image, DROP cover_word, DROP extra_plans');
    }
}
