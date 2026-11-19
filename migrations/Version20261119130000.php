<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour l'enregistrement des appareils mobiles Expo Push (mobile_push_device).
 */
final class Version20261119130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table mobile_push_device pour les notifications Expo Push';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobile_push_device (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expo_token VARCHAR(255) NOT NULL, platform VARCHAR(20) NOT NULL, device_name VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, INDEX idx_mobile_push_user (user_id), UNIQUE INDEX uniq_mobile_push_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mobile_push_device ADD CONSTRAINT FK_MOBILE_PUSH_DEVICE_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mobile_push_device DROP FOREIGN KEY FK_MOBILE_PUSH_DEVICE_USER');
        $this->addSql('DROP TABLE mobile_push_device');
    }
}
