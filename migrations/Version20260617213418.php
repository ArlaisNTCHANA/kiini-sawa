<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617213418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hygiene_domain_life_goal (hygiene_domain_id INT NOT NULL, life_goal_id INT NOT NULL, INDEX IDX_F036A00AE9C645D5 (hygiene_domain_id), INDEX IDX_F036A00AC00EDB6D (life_goal_id), PRIMARY KEY (hygiene_domain_id, life_goal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE legacy_goal_life_goal (legacy_goal_id INT NOT NULL, life_goal_id INT NOT NULL, INDEX IDX_332E8A8B8182CE6 (legacy_goal_id), INDEX IDX_332E8A8C00EDB6D (life_goal_id), PRIMARY KEY (legacy_goal_id, life_goal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hygiene_domain_life_goal ADD CONSTRAINT FK_F036A00AE9C645D5 FOREIGN KEY (hygiene_domain_id) REFERENCES hygiene_domain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hygiene_domain_life_goal ADD CONSTRAINT FK_F036A00AC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE legacy_goal_life_goal ADD CONSTRAINT FK_332E8A8B8182CE6 FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE legacy_goal_life_goal ADD CONSTRAINT FK_332E8A8C00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hygiene_domain ADD subtype VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE legacy_goal ADD subtype VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hygiene_domain_life_goal DROP FOREIGN KEY FK_F036A00AE9C645D5');
        $this->addSql('ALTER TABLE hygiene_domain_life_goal DROP FOREIGN KEY FK_F036A00AC00EDB6D');
        $this->addSql('ALTER TABLE legacy_goal_life_goal DROP FOREIGN KEY FK_332E8A8B8182CE6');
        $this->addSql('ALTER TABLE legacy_goal_life_goal DROP FOREIGN KEY FK_332E8A8C00EDB6D');
        $this->addSql('DROP TABLE hygiene_domain_life_goal');
        $this->addSql('DROP TABLE legacy_goal_life_goal');
        $this->addSql('ALTER TABLE hygiene_domain DROP subtype');
        $this->addSql('ALTER TABLE legacy_goal DROP subtype');
    }
}
