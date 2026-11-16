<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Parrainage. */
final class Version20261117080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table referral (parrainage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE referral (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, rewarded_at DATETIME DEFAULT NULL, referrer_id INT NOT NULL, referred_id INT NOT NULL, UNIQUE INDEX UNIQ_73079D00CFE2A98 (referred_id), INDEX idx_referral_referrer (referrer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D00798C22DB FOREIGN KEY (referrer_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D00CFE2A98 FOREIGN KEY (referred_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE referral');
    }
}
