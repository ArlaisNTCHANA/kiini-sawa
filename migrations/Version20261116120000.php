<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table des sessions (PdoSessionHandler), utilisée en production pour que les connexions
 * survivent aux déploiements.
 */
final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table des sessions en base de données';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS sessions (sess_id VARBINARY(128) NOT NULL PRIMARY KEY, sess_data LONGBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sessions_sess_lifetime_idx (sess_lifetime)) COLLATE utf8mb4_bin, ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
