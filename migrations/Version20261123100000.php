<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Engagements de redevabilité (accountability_commitment) et interpellations (accountability_nudge)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accountability_commitment (id INT AUTO_INCREMENT NOT NULL, partner_name VARCHAR(120) NOT NULL, partner_email VARCHAR(180) NOT NULL, target_type VARCHAR(20) NOT NULL, target_id INT DEFAULT NULL, target_label VARCHAR(255) NOT NULL, why LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, partner_token VARCHAR(48) NOT NULL, min_days_between_nudges INT NOT NULL, owner_consent_at DATETIME NOT NULL, partner_accepted_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, ended_by VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, partner_user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_7CDE0FCB4C0B0D5E (partner_token), INDEX IDX_7CDE0FCB7E3C61F9 (owner_id), INDEX IDX_7CDE0FCB6084B088 (partner_user_id), INDEX idx_accountability_owner (owner_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE accountability_nudge (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(20) NOT NULL, owner_message LONGTEXT DEFAULT NULL, token VARCHAR(48) NOT NULL, sent_at DATETIME NOT NULL, responded_at DATETIME DEFAULT NULL, response_action VARCHAR(20) DEFAULT NULL, response_note LONGTEXT DEFAULT NULL, commitment_id INT NOT NULL, UNIQUE INDEX UNIQ_45129AE45F37A13B (token), INDEX IDX_45129AE4680FAE08 (commitment_id), INDEX idx_accountability_nudge_sent (commitment_id, sent_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE accountability_commitment ADD CONSTRAINT FK_7CDE0FCB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accountability_commitment ADD CONSTRAINT FK_7CDE0FCB6084B088 FOREIGN KEY (partner_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE accountability_nudge ADD CONSTRAINT FK_45129AE4680FAE08 FOREIGN KEY (commitment_id) REFERENCES accountability_commitment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accountability_commitment DROP FOREIGN KEY FK_7CDE0FCB7E3C61F9');
        $this->addSql('ALTER TABLE accountability_commitment DROP FOREIGN KEY FK_7CDE0FCB6084B088');
        $this->addSql('ALTER TABLE accountability_nudge DROP FOREIGN KEY FK_45129AE4680FAE08');
        $this->addSql('DROP TABLE accountability_commitment');
        $this->addSql('DROP TABLE accountability_nudge');
    }
}
