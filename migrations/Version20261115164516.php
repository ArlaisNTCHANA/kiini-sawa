<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115164516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bilan : revue hebdomadaire sur les quatre plans';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE weekly_review (id INT AUTO_INCREMENT NOT NULL, week_start DATE NOT NULL, wins LONGTEXT DEFAULT NULL, struggles LONGTEXT DEFAULT NULL, lesson LONGTEXT DEFAULT NULL, priorities JSON NOT NULL, items JSON NOT NULL, completed_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_64D2A38BA76ED395 (user_id), UNIQUE INDEX uniq_weekly_review_user_week (user_id, week_start), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE weekly_review ADD CONSTRAINT FK_64D2A38BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE weekly_review DROP FOREIGN KEY FK_64D2A38BA76ED395');
        $this->addSql('DROP TABLE weekly_review');
    }
}
