<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611164937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journal_collection (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(50) NOT NULL, color VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_612D7F26A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reflection_note (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, date DATETIME NOT NULL, mood VARCHAR(100) DEFAULT NULL, energy INT DEFAULT NULL, type VARCHAR(50) NOT NULL, location VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, collection_id INT DEFAULT NULL, goal_id INT DEFAULT NULL, growth_goal_id INT DEFAULT NULL, INDEX IDX_903B2EE0A76ED395 (user_id), INDEX IDX_903B2EE0514956FD (collection_id), INDEX IDX_903B2EE0667D1AFE (goal_id), INDEX IDX_903B2EE0D2035C (growth_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE journal_collection ADD CONSTRAINT FK_612D7F26A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reflection_note ADD CONSTRAINT FK_903B2EE0A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reflection_note ADD CONSTRAINT FK_903B2EE0514956FD FOREIGN KEY (collection_id) REFERENCES journal_collection (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reflection_note ADD CONSTRAINT FK_903B2EE0667D1AFE FOREIGN KEY (goal_id) REFERENCES life_goal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reflection_note ADD CONSTRAINT FK_903B2EE0D2035C FOREIGN KEY (growth_goal_id) REFERENCES growth_goal (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journal_collection DROP FOREIGN KEY FK_612D7F26A76ED395');
        $this->addSql('ALTER TABLE reflection_note DROP FOREIGN KEY FK_903B2EE0A76ED395');
        $this->addSql('ALTER TABLE reflection_note DROP FOREIGN KEY FK_903B2EE0514956FD');
        $this->addSql('ALTER TABLE reflection_note DROP FOREIGN KEY FK_903B2EE0667D1AFE');
        $this->addSql('ALTER TABLE reflection_note DROP FOREIGN KEY FK_903B2EE0D2035C');
        $this->addSql('DROP TABLE journal_collection');
        $this->addSql('DROP TABLE reflection_note');
    }
}
