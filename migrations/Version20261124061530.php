<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Feedback nourricier : date de fin des liens (une semaine, prolongeable). Les liens ouverts reçoivent une semaine à partir d'aujourd'hui. */
final class Version20261124061530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Feedback nourricier : date d\'expiration des liens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_request ADD expires_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE feedback_request SET expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE is_active = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_request DROP expires_at');
    }
}
