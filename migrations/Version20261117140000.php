<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Confirmation des résultats de tests par les proches (DISC, dons spirituels). */
final class Version20261117140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables confirmation_request et confirmation_answer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE confirmation_request (id INT AUTO_INCREMENT NOT NULL, instrument VARCHAR(30) NOT NULL, token VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_BD9BA2CC5F37A13B (token), INDEX IDX_BD9BA2CCA76ED395 (user_id), INDEX idx_confirmation_user (user_id, instrument), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE confirmation_answer (id INT AUTO_INCREMENT NOT NULL, relationship VARCHAR(30) NOT NULL, payload JSON NOT NULL, name VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, request_id INT NOT NULL, INDEX IDX_522CDF5427EB8A5 (request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE confirmation_request ADD CONSTRAINT FK_BD9BA2CCA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE confirmation_answer ADD CONSTRAINT FK_522CDF5427EB8A5 FOREIGN KEY (request_id) REFERENCES confirmation_request (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE confirmation_answer');
        $this->addSql('DROP TABLE confirmation_request');
    }
}
