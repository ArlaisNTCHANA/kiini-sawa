<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115173208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations : organisations, membres, commandes de places';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(10) NOT NULL, share_plans TINYINT NOT NULL, active TINYINT NOT NULL, joined_at DATETIME NOT NULL, organisation_id INT NOT NULL, user_id INT NOT NULL, mentor_id INT DEFAULT NULL, INDEX IDX_86FFD2859E6B1585 (organisation_id), INDEX IDX_86FFD285A76ED395 (user_id), INDEX IDX_86FFD285DB403044 (mentor_id), UNIQUE INDEX uniq_membership_org_user (organisation_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organisation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL, seats INT NOT NULL, seats_valid_until DATE DEFAULT NULL, invite_code VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_E6E132B46F21F112 (invite_code), INDEX IDX_E6E132B4B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seat_order (id INT AUTO_INCREMENT NOT NULL, seats INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(10) NOT NULL, method VARCHAR(30) NOT NULL, reference VARCHAR(255) DEFAULT NULL, status VARCHAR(10) NOT NULL, receipt_number VARCHAR(30) DEFAULT NULL, admin_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, organisation_id INT NOT NULL, ordered_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_22B518BCB0ADB74C (receipt_number), INDEX IDX_22B518BC9E6B1585 (organisation_id), INDEX IDX_22B518BC91FF3C4D (ordered_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD2859E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285DB403044 FOREIGN KEY (mentor_id) REFERENCES membership (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organisation ADD CONSTRAINT FK_E6E132B4B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE seat_order ADD CONSTRAINT FK_22B518BC9E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seat_order ADD CONSTRAINT FK_22B518BC91FF3C4D FOREIGN KEY (ordered_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD2859E6B1585');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285A76ED395');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285DB403044');
        $this->addSql('ALTER TABLE organisation DROP FOREIGN KEY FK_E6E132B4B03A8386');
        $this->addSql('ALTER TABLE seat_order DROP FOREIGN KEY FK_22B518BC9E6B1585');
        $this->addSql('ALTER TABLE seat_order DROP FOREIGN KEY FK_22B518BC91FF3C4D');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE organisation');
        $this->addSql('DROP TABLE seat_order');
    }
}
