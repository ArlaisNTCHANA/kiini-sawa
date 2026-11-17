<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117145446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plan d\'Intendance : tables stewardship_item et stewardship_rhythm, interrupteur gratuit/PRO';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stewardship_item (id INT AUTO_INCREMENT NOT NULL, origin VARCHAR(30) NOT NULL, label VARCHAR(255) NOT NULL, note LONGTEXT DEFAULT NULL, status VARCHAR(12) DEFAULT NULL, decision VARCHAR(12) DEFAULT NULL, action_text VARCHAR(255) DEFAULT NULL, priority TINYINT DEFAULT 0 NOT NULL, action_done_at DATETIME DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_CF49E547A76ED395 (user_id), INDEX idx_stewardship_user (user_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stewardship_rhythm (id INT AUTO_INCREMENT NOT NULL, frequency VARCHAR(10) NOT NULL, with_whom VARCHAR(120) DEFAULT NULL, last_review_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_759F16CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stewardship_item ADD CONSTRAINT FK_CF49E547A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stewardship_rhythm ADD CONSTRAINT FK_759F16CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_config ADD enable_stewardship_free TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stewardship_item DROP FOREIGN KEY FK_CF49E547A76ED395');
        $this->addSql('ALTER TABLE stewardship_rhythm DROP FOREIGN KEY FK_759F16CAA76ED395');
        $this->addSql('DROP TABLE stewardship_item');
        $this->addSql('DROP TABLE stewardship_rhythm');
        $this->addSql('ALTER TABLE app_config DROP enable_stewardship_free');
    }
}
