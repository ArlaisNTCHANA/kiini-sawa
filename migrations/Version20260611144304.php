<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611144304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE life_goal (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, deadline DATETIME DEFAULT NULL, metric VARCHAR(255) DEFAULT NULL, progress INT NOT NULL, is_shared TINYINT NOT NULL, plan_de_vie_id INT NOT NULL, INDEX IDX_447E5690C66FEE61 (plan_de_vie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE life_milestone (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, due_date DATETIME NOT NULL, completed TINYINT NOT NULL, life_goal_id INT NOT NULL, INDEX IDX_F9A30E0EC00EDB6D (life_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE passion (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, context LONGTEXT DEFAULT NULL, plan_de_vie_id INT NOT NULL, INDEX IDX_7A3D8D5FC66FEE61 (plan_de_vie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purpose (id INT AUTO_INCREMENT NOT NULL, statement LONGTEXT NOT NULL, long_statement LONGTEXT DEFAULT NULL, hypotheses LONGTEXT DEFAULT NULL, detail LONGTEXT DEFAULT NULL, proofs LONGTEXT DEFAULT NULL, plan_de_vie_id INT NOT NULL, UNIQUE INDEX UNIQ_B887B3EBC66FEE61 (plan_de_vie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE value (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, behavior LONGTEXT NOT NULL, plan_de_vie_id INT NOT NULL, INDEX IDX_1D775834C66FEE61 (plan_de_vie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vision (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, horizon VARCHAR(255) NOT NULL, beneficiaries LONGTEXT NOT NULL, indicators LONGTEXT NOT NULL, images LONGTEXT DEFAULT NULL, plan_de_vie_id INT NOT NULL, UNIQUE INDEX UNIQ_8E789EA9C66FEE61 (plan_de_vie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE life_goal ADD CONSTRAINT FK_447E5690C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id)');
        $this->addSql('ALTER TABLE life_milestone ADD CONSTRAINT FK_F9A30E0EC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id)');
        $this->addSql('ALTER TABLE passion ADD CONSTRAINT FK_7A3D8D5FC66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id)');
        $this->addSql('ALTER TABLE purpose ADD CONSTRAINT FK_B887B3EBC66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id)');
        $this->addSql('ALTER TABLE value ADD CONSTRAINT FK_1D775834C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id)');
        $this->addSql('ALTER TABLE vision ADD CONSTRAINT FK_8E789EA9C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE life_goal DROP FOREIGN KEY FK_447E5690C66FEE61');
        $this->addSql('ALTER TABLE life_milestone DROP FOREIGN KEY FK_F9A30E0EC00EDB6D');
        $this->addSql('ALTER TABLE passion DROP FOREIGN KEY FK_7A3D8D5FC66FEE61');
        $this->addSql('ALTER TABLE purpose DROP FOREIGN KEY FK_B887B3EBC66FEE61');
        $this->addSql('ALTER TABLE value DROP FOREIGN KEY FK_1D775834C66FEE61');
        $this->addSql('ALTER TABLE vision DROP FOREIGN KEY FK_8E789EA9C66FEE61');
        $this->addSql('DROP TABLE life_goal');
        $this->addSql('DROP TABLE life_milestone');
        $this->addSql('DROP TABLE passion');
        $this->addSql('DROP TABLE purpose');
        $this->addSql('DROP TABLE value');
        $this->addSql('DROP TABLE vision');
    }
}
