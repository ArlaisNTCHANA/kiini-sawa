<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115182624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tests : passations (réponses item par item, scores, qualité)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assessment (id INT AUTO_INCREMENT NOT NULL, instrument VARCHAR(30) NOT NULL, answers JSON NOT NULL, scores JSON NOT NULL, quality JSON NOT NULL, duration_seconds INT DEFAULT NULL, taken_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_F7523D70A76ED395 (user_id), INDEX idx_assessment_instrument (instrument), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assessment ADD CONSTRAINT FK_F7523D70A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment DROP FOREIGN KEY FK_F7523D70A76ED395');
        $this->addSql('DROP TABLE assessment');
    }
}
