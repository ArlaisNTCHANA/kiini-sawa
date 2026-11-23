<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sanctuaire du Couple : temps de retraite (couple_retreat) et double validation des étapes de méditation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE couple_retreat (id INT AUTO_INCREMENT NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, couple_id INT NOT NULL, proposed_by_id INT DEFAULT NULL, confirmed_by_id INT DEFAULT NULL, INDEX IDX_905E2ECDF66468CA (couple_id), INDEX IDX_905E2ECDDAB5A938 (proposed_by_id), INDEX IDX_905E2ECD6F45385D (confirmed_by_id), INDEX idx_couple_retreat_dates (couple_id, ends_on), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE couple_retreat ADD CONSTRAINT FK_905E2ECDF66468CA FOREIGN KEY (couple_id) REFERENCES couple (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple_retreat ADD CONSTRAINT FK_905E2ECDDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE couple_retreat ADD CONSTRAINT FK_905E2ECD6F45385D FOREIGN KEY (confirmed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE couple_meditation ADD step_validated_by_partner_a TINYINT DEFAULT 0 NOT NULL, ADD step_validated_by_partner_b TINYINT DEFAULT 0 NOT NULL, ADD last_step_completed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE couple_retreat DROP FOREIGN KEY FK_905E2ECDF66468CA');
        $this->addSql('ALTER TABLE couple_retreat DROP FOREIGN KEY FK_905E2ECDDAB5A938');
        $this->addSql('ALTER TABLE couple_retreat DROP FOREIGN KEY FK_905E2ECD6F45385D');
        $this->addSql('DROP TABLE couple_retreat');
        $this->addSql('ALTER TABLE couple_meditation DROP step_validated_by_partner_a, DROP step_validated_by_partner_b, DROP last_step_completed_at');
    }
}
