<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118203319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mode rapide : opérations de synchronisation reçues du téléphone (idempotence)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sync_operation (id INT AUTO_INCREMENT NOT NULL, uid VARCHAR(64) NOT NULL, type VARCHAR(30) NOT NULL, applied_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_C516B91CA76ED395 (user_id), UNIQUE INDEX uniq_sync_operation_user_uid (user_id, uid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sync_operation ADD CONSTRAINT FK_C516B91CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sync_operation DROP FOREIGN KEY FK_C516B91CA76ED395');
        $this->addSql('DROP TABLE sync_operation');
    }
}
