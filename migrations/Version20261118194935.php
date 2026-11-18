<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118194935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contenu : textes du site modifiables (brouillon, publication, historique)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site_text (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(190) NOT NULL, published LONGTEXT DEFAULT NULL, draft LONGTEXT DEFAULT NULL, published_at DATETIME DEFAULT NULL, draft_at DATETIME DEFAULT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_111A3E77153098 (code), INDEX IDX_111A3E896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_text_version (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(190) NOT NULL, content LONGTEXT NOT NULL, kind VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_5037614FF675F31B (author_id), INDEX idx_site_text_version_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_text ADD CONSTRAINT FK_111A3E896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE site_text_version ADD CONSTRAINT FK_5037614FF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_text DROP FOREIGN KEY FK_111A3E896DBBDE');
        $this->addSql('ALTER TABLE site_text_version DROP FOREIGN KEY FK_5037614FF675F31B');
        $this->addSql('DROP TABLE site_text');
        $this->addSql('DROP TABLE site_text_version');
    }
}
