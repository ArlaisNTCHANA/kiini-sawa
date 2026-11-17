<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117152342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plan d\'Intendance : lien facultatif vers un objectif de vie (carte stratégique)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stewardship_item ADD life_goal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stewardship_item ADD CONSTRAINT FK_CF49E547C00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CF49E547C00EDB6D ON stewardship_item (life_goal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stewardship_item DROP FOREIGN KEY FK_CF49E547C00EDB6D');
        $this->addSql('DROP INDEX IDX_CF49E547C00EDB6D ON stewardship_item');
        $this->addSql('ALTER TABLE stewardship_item DROP life_goal_id');
    }
}
