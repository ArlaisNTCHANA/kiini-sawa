<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611164231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advice_request (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, point_id INT DEFAULT NULL, INDEX IDX_E814E1EAA76ED395 (user_id), INDEX IDX_E814E1EAC028CEA2 (point_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE advice_response (id INT AUTO_INCREMENT NOT NULL, provider_name VARCHAR(255) DEFAULT NULL, provider_email VARCHAR(255) DEFAULT NULL, q1_observation LONGTEXT DEFAULT NULL, q2_risk LONGTEXT DEFAULT NULL, q3_advice LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, request_id INT NOT NULL, INDEX IDX_1EC70D26427EB8A5 (request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE coaching_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, context VARCHAR(255) DEFAULT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_1D53481FF624B39D (sender_id), INDEX IDX_1D53481FCD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE connection (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, share_scope VARCHAR(50) NOT NULL, requester_share_life_plan TINYINT NOT NULL, requester_share_growth_plan TINYINT NOT NULL, requester_share_habits TINYINT NOT NULL, requester_share_reviews TINYINT NOT NULL, requester_share_discovery TINYINT NOT NULL, requester_share_croissance TINYINT NOT NULL, requester_share_developpement TINYINT NOT NULL, requester_share_hygiene TINYINT NOT NULL, requester_share_transmission TINYINT NOT NULL, requester_share_bilan TINYINT NOT NULL, receiver_share_life_plan TINYINT NOT NULL, receiver_share_growth_plan TINYINT NOT NULL, receiver_share_habits TINYINT NOT NULL, receiver_share_reviews TINYINT NOT NULL, receiver_share_discovery TINYINT NOT NULL, receiver_share_croissance TINYINT NOT NULL, receiver_share_developpement TINYINT NOT NULL, receiver_share_hygiene TINYINT NOT NULL, receiver_share_transmission TINYINT NOT NULL, receiver_share_bilan TINYINT NOT NULL, created_at DATETIME NOT NULL, requester_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_29F77366ED442CF4 (requester_id), INDEX IDX_29F77366CD53EDB6 (receiver_id), UNIQUE INDEX UNIQ_REQUESTER_RECEIVER (requester_id, receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE goal_suggestion (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, from_user_id INT NOT NULL, to_user_id INT NOT NULL, INDEX IDX_FFD4E21C2130303A (from_user_id), INDEX IDX_FFD4E21C29F6EE60 (to_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE advice_request ADD CONSTRAINT FK_E814E1EAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE advice_request ADD CONSTRAINT FK_E814E1EAC028CEA2 FOREIGN KEY (point_id) REFERENCES confirm_point (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE advice_response ADD CONSTRAINT FK_1EC70D26427EB8A5 FOREIGN KEY (request_id) REFERENCES advice_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coaching_message ADD CONSTRAINT FK_1D53481FF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coaching_message ADD CONSTRAINT FK_1D53481FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE connection ADD CONSTRAINT FK_29F77366ED442CF4 FOREIGN KEY (requester_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE connection ADD CONSTRAINT FK_29F77366CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE goal_suggestion ADD CONSTRAINT FK_FFD4E21C2130303A FOREIGN KEY (from_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE goal_suggestion ADD CONSTRAINT FK_FFD4E21C29F6EE60 FOREIGN KEY (to_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advice_request DROP FOREIGN KEY FK_E814E1EAA76ED395');
        $this->addSql('ALTER TABLE advice_request DROP FOREIGN KEY FK_E814E1EAC028CEA2');
        $this->addSql('ALTER TABLE advice_response DROP FOREIGN KEY FK_1EC70D26427EB8A5');
        $this->addSql('ALTER TABLE coaching_message DROP FOREIGN KEY FK_1D53481FF624B39D');
        $this->addSql('ALTER TABLE coaching_message DROP FOREIGN KEY FK_1D53481FCD53EDB6');
        $this->addSql('ALTER TABLE connection DROP FOREIGN KEY FK_29F77366ED442CF4');
        $this->addSql('ALTER TABLE connection DROP FOREIGN KEY FK_29F77366CD53EDB6');
        $this->addSql('ALTER TABLE goal_suggestion DROP FOREIGN KEY FK_FFD4E21C2130303A');
        $this->addSql('ALTER TABLE goal_suggestion DROP FOREIGN KEY FK_FFD4E21C29F6EE60');
        $this->addSql('DROP TABLE advice_request');
        $this->addSql('DROP TABLE advice_response');
        $this->addSql('DROP TABLE coaching_message');
        $this->addSql('DROP TABLE connection');
        $this->addSql('DROP TABLE goal_suggestion');
    }
}
