<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117155519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parcours de 90 jours (livre, annexe A) : table journey_progress';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journey_progress (id INT AUTO_INCREMENT NOT NULL, started_at DATE NOT NULL, completed_weeks JSON NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_83EF66D1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE journey_progress ADD CONSTRAINT FK_83EF66D1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journey_progress DROP FOREIGN KEY FK_83EF66D1A76ED395');
        $this->addSql('DROP TABLE journey_progress');
    }
}
