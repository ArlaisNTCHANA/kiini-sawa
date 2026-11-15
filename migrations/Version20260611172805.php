<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611172805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE internal_balance (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, subtype VARCHAR(100) DEFAULT NULL, text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_B4EC5EC8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE principle (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, pillar VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_ECD24AB2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shared_link (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, scope VARCHAR(100) DEFAULT \'FULL\' NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8C5AF2695F37A13B (token), INDEX IDX_8C5AF269A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE internal_balance ADD CONSTRAINT FK_B4EC5EC8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE principle ADD CONSTRAINT FK_ECD24AB2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_link ADD CONSTRAINT FK_8C5AF269A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE internal_balance DROP FOREIGN KEY FK_B4EC5EC8A76ED395');
        $this->addSql('ALTER TABLE principle DROP FOREIGN KEY FK_ECD24AB2A76ED395');
        $this->addSql('ALTER TABLE shared_link DROP FOREIGN KEY FK_8C5AF269A76ED395');
        $this->addSql('DROP TABLE internal_balance');
        $this->addSql('DROP TABLE principle');
        $this->addSql('DROP TABLE shared_link');
    }
}
