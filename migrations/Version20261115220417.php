<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115220417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Espace couple : couples, éléments communs, revues';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE couple (id INT AUTO_INCREMENT NOT NULL, invited_email VARCHAR(180) DEFAULT NULL, invite_code VARCHAR(32) NOT NULL, vision LONGTEXT DEFAULT NULL, verse VARCHAR(500) DEFAULT NULL, share_tests_a TINYINT NOT NULL, share_tests_b TINYINT NOT NULL, created_at DATETIME NOT NULL, joined_at DATETIME DEFAULT NULL, partner_a_id INT NOT NULL, partner_b_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D840B5496F21F112 (invite_code), INDEX IDX_D840B5496BEA6040 (partner_a_id), INDEX IDX_D840B549795FCFAE (partner_b_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE couple_item (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, category VARCHAR(30) DEFAULT NULL, deadline DATE DEFAULT NULL, progress INT NOT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL, couple_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_40A1342F66468CA (couple_id), INDEX IDX_40A1342B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE couple_review (id INT AUTO_INCREMENT NOT NULL, month DATE NOT NULL, answers JSON NOT NULL, updated_at DATETIME NOT NULL, couple_id INT NOT NULL, INDEX IDX_A0F71D16F66468CA (couple_id), UNIQUE INDEX uniq_couple_review_month (couple_id, month), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE couple ADD CONSTRAINT FK_D840B5496BEA6040 FOREIGN KEY (partner_a_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple ADD CONSTRAINT FK_D840B549795FCFAE FOREIGN KEY (partner_b_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple_item ADD CONSTRAINT FK_40A1342F66468CA FOREIGN KEY (couple_id) REFERENCES couple (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple_item ADD CONSTRAINT FK_40A1342B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE couple_review ADD CONSTRAINT FK_A0F71D16F66468CA FOREIGN KEY (couple_id) REFERENCES couple (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE couple DROP FOREIGN KEY FK_D840B5496BEA6040');
        $this->addSql('ALTER TABLE couple DROP FOREIGN KEY FK_D840B549795FCFAE');
        $this->addSql('ALTER TABLE couple_item DROP FOREIGN KEY FK_40A1342F66468CA');
        $this->addSql('ALTER TABLE couple_item DROP FOREIGN KEY FK_40A1342B03A8386');
        $this->addSql('ALTER TABLE couple_review DROP FOREIGN KEY FK_A0F71D16F66468CA');
        $this->addSql('DROP TABLE couple');
        $this->addSql('DROP TABLE couple_item');
        $this->addSql('DROP TABLE couple_review');
    }
}
