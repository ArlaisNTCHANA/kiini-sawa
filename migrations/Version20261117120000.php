<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Textes des tests modifiés depuis l'administration. */
final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table content_override';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE content_override (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(190) NOT NULL, content LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D0B4EC0AB548B0F (path), INDEX IDX_D0B4EC0A896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE content_override ADD CONSTRAINT FK_CONTENT_OVERRIDE_USER FOREIGN KEY (updated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_override');
    }
}
