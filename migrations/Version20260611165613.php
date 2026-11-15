<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611165613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE check_in (id INT AUTO_INCREMENT NOT NULL, date DATETIME NOT NULL, done LONGTEXT NOT NULL, not_done LONGTEXT NOT NULL, blocker LONGTEXT DEFAULT NULL, adjustment LONGTEXT DEFAULT NULL, action48h LONGTEXT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_90466CF9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE monthly_review (id INT AUTO_INCREMENT NOT NULL, month DATETIME NOT NULL, progress LONGTEXT DEFAULT NULL, stagnation LONGTEXT DEFAULT NULL, blocker LONGTEXT DEFAULT NULL, adjustment LONGTEXT DEFAULT NULL, decision LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_9AE3120DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan_history (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, data LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_99053843A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE check_in ADD CONSTRAINT FK_90466CF9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE monthly_review ADD CONSTRAINT FK_9AE3120DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_history ADD CONSTRAINT FK_99053843A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE check_in DROP FOREIGN KEY FK_90466CF9A76ED395');
        $this->addSql('ALTER TABLE monthly_review DROP FOREIGN KEY FK_9AE3120DA76ED395');
        $this->addSql('ALTER TABLE plan_history DROP FOREIGN KEY FK_99053843A76ED395');
        $this->addSql('DROP TABLE check_in');
        $this->addSql('DROP TABLE monthly_review');
        $this->addSql('DROP TABLE plan_history');
    }
}
