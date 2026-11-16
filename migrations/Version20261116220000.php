<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Défis collectifs : adresse publique, accroche, actions du jour, publication. */
final class Version20261116220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Défis collectifs : slug, tagline, daily_actions, published';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE challenge ADD slug VARCHAR(80) DEFAULT NULL, ADD tagline VARCHAR(255) DEFAULT NULL, ADD daily_actions JSON DEFAULT NULL, ADD published TINYINT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE challenge SET daily_actions = '[]' WHERE daily_actions IS NULL");
        $this->addSql('ALTER TABLE challenge MODIFY daily_actions JSON NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D7098951989D9B62 ON challenge (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_D7098951989D9B62 ON challenge');
        $this->addSql('ALTER TABLE challenge DROP slug, DROP tagline, DROP daily_actions, DROP published');
    }
}
