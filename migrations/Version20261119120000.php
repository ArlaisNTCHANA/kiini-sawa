<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour l'authentification mobile par jeton et la source d'abonnement (prête pour IAP futur).
 */
final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jetons mobiles Bearer (mobile_token) et colonne billing_source sur subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobile_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, device_name VARCHAR(120) NOT NULL, access_hash VARCHAR(64) NOT NULL, refresh_hash VARCHAR(64) NOT NULL, access_expires_at DATETIME NOT NULL, refresh_expires_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, rotated TINYINT DEFAULT 0 NOT NULL, INDEX idx_mobile_token_user (user_id), UNIQUE INDEX uniq_mobile_token_access_hash (access_hash), UNIQUE INDEX uniq_mobile_token_refresh_hash (refresh_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mobile_token ADD CONSTRAINT FK_MOBILE_TOKEN_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription ADD billing_source VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mobile_token DROP FOREIGN KEY FK_MOBILE_TOKEN_USER');
        $this->addSql('DROP TABLE mobile_token');
        $this->addSql('ALTER TABLE subscription DROP billing_source');
    }
}
