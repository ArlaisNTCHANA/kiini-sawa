<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Partage public d'un résultat de test. */
final class Version20261117090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'assessment.share_token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assessment ADD share_token VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7523D70D6594DD6 ON assessment (share_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_F7523D70D6594DD6 ON assessment');
        $this->addSql('ALTER TABLE assessment DROP share_token');
    }
}
