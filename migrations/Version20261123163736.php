<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123163736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boussole 360 : passations enregistrées dans le compte (historique)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boussole_result (id INT AUTO_INCREMENT NOT NULL, answers JSON NOT NULL, scores JSON NOT NULL, coherence SMALLINT NOT NULL, strongest_key VARCHAR(20) NOT NULL, priority_key VARCHAR(20) NOT NULL, source VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_1681E57A76ED395 (user_id), INDEX idx_boussole_user_date (user_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE boussole_result ADD CONSTRAINT FK_1681E57A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE boussole_result DROP FOREIGN KEY FK_1681E57A76ED395');
        $this->addSql('DROP TABLE boussole_result');
    }
}
