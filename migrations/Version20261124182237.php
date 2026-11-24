<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124182237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Atelier de schémas de l\'Académie : schémas créés dans le studio';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE academy_diagram (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(80) NOT NULL, title VARCHAR(120) NOT NULL, model VARCHAR(20) NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_BB361EF277153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE academy_diagram');
    }
}
