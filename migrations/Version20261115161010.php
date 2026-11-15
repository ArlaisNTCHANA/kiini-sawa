<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115161010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte : vérification email, consentement, jetons ; suppression en cascade des données d\'un utilisateur';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_token (id INT AUTO_INCREMENT NOT NULL, purpose VARCHAR(20) NOT NULL, selector VARCHAR(32) DEFAULT NULL, secret_hash VARCHAR(64) NOT NULL, payload VARCHAR(180) DEFAULT NULL, attempts INT NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_69B8784AA76ED395 (user_id), INDEX idx_account_token_selector (selector), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE account_token ADD CONSTRAINT FK_69B8784AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE career_step DROP FOREIGN KEY `FK_9D10DDA546D52AA2`');
        $this->addSql('ALTER TABLE career_step ADD CONSTRAINT FK_9D10DDA546D52AA2 FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE confirm_point DROP FOREIGN KEY `FK_405BF84E9C828A73`');
        $this->addSql('ALTER TABLE confirm_point ADD CONSTRAINT FK_405BF84E9C828A73 FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE daily_log DROP FOREIGN KEY `FK_8D0D8EA9CEC05175`');
        $this->addSql('ALTER TABLE daily_log ADD CONSTRAINT FK_8D0D8EA9CEC05175 FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE growth_goal DROP FOREIGN KEY `FK_5EC0A4299C828A73`');
        $this->addSql('ALTER TABLE growth_goal ADD CONSTRAINT FK_5EC0A4299C828A73 FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY `FK_44FE2172A76ED395`');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT FK_44FE2172A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hygiene_domain DROP FOREIGN KEY `FK_DC9FAEA5CEC05175`');
        $this->addSql('ALTER TABLE hygiene_domain ADD CONSTRAINT FK_DC9FAEA5CEC05175 FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE learning_resource DROP FOREIGN KEY `FK_98E1F0087C8884D7`');
        $this->addSql('ALTER TABLE learning_resource ADD CONSTRAINT FK_98E1F0087C8884D7 FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE legacy_goal DROP FOREIGN KEY `FK_D36C5036868E2FF1`');
        $this->addSql('ALTER TABLE legacy_goal ADD CONSTRAINT FK_D36C5036868E2FF1 FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE life_goal DROP FOREIGN KEY `FK_447E5690C66FEE61`');
        $this->addSql('ALTER TABLE life_goal ADD CONSTRAINT FK_447E5690C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE life_milestone DROP FOREIGN KEY `FK_F9A30E0EC00EDB6D`');
        $this->addSql('ALTER TABLE life_milestone ADD CONSTRAINT FK_F9A30E0EC00EDB6D FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE passion DROP FOREIGN KEY `FK_7A3D8D5FC66FEE61`');
        $this->addSql('ALTER TABLE passion ADD CONSTRAINT FK_7A3D8D5FC66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_de_croissance DROP FOREIGN KEY `FK_E6F7090DA76ED395`');
        $this->addSql('ALTER TABLE plan_de_croissance ADD CONSTRAINT FK_E6F7090DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_de_developpement_personnel DROP FOREIGN KEY `FK_66004F72A76ED395`');
        $this->addSql('ALTER TABLE plan_de_developpement_personnel ADD CONSTRAINT FK_66004F72A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_de_transmission DROP FOREIGN KEY `FK_DD2DC8E9A76ED395`');
        $this->addSql('ALTER TABLE plan_de_transmission ADD CONSTRAINT FK_DD2DC8E9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_de_vie DROP FOREIGN KEY `FK_8DD8428FA76ED395`');
        $this->addSql('ALTER TABLE plan_de_vie ADD CONSTRAINT FK_8DD8428FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_dhygiene DROP FOREIGN KEY `FK_4E14D138A76ED395`');
        $this->addSql('ALTER TABLE plan_dhygiene ADD CONSTRAINT FK_4E14D138A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purpose DROP FOREIGN KEY `FK_B887B3EBC66FEE61`');
        $this->addSql('ALTER TABLE purpose ADD CONSTRAINT FK_B887B3EBC66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE skill_goal DROP FOREIGN KEY `FK_BB9D779C46D52AA2`');
        $this->addSql('ALTER TABLE skill_goal ADD CONSTRAINT FK_BB9D779C46D52AA2 FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY `FK_A3C664D3A76ED395`');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE talent_don DROP FOREIGN KEY `FK_A38961DF868E2FF1`');
        $this->addSql('ALTER TABLE talent_don ADD CONSTRAINT FK_A38961DF868E2FF1 FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY `FK_723705D19A1887DC`');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D19A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transmission_action DROP FOREIGN KEY `FK_B33CE2DFB8182CE6`');
        $this->addSql('ALTER TABLE transmission_action ADD CONSTRAINT FK_B33CE2DFB8182CE6 FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT DEFAULT 0 NOT NULL, ADD created_at DATETIME DEFAULT NULL, ADD terms_accepted_at DATETIME DEFAULT NULL');
        // Les comptes existants ont été créés à la main par un administrateur : on les considère vérifiés
        $this->addSql('UPDATE `user` SET is_verified = 1');
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY `FK_D95AB405A76ED395`');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT FK_D95AB405A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE value DROP FOREIGN KEY `FK_1D775834C66FEE61`');
        $this->addSql('ALTER TABLE value ADD CONSTRAINT FK_1D775834C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vision DROP FOREIGN KEY `FK_8E789EA9C66FEE61`');
        $this->addSql('ALTER TABLE vision ADD CONSTRAINT FK_8E789EA9C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account_token DROP FOREIGN KEY FK_69B8784AA76ED395');
        $this->addSql('DROP TABLE account_token');
        $this->addSql('ALTER TABLE career_step DROP FOREIGN KEY FK_9D10DDA546D52AA2');
        $this->addSql('ALTER TABLE career_step ADD CONSTRAINT `FK_9D10DDA546D52AA2` FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE confirm_point DROP FOREIGN KEY FK_405BF84E9C828A73');
        $this->addSql('ALTER TABLE confirm_point ADD CONSTRAINT `FK_405BF84E9C828A73` FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE daily_log DROP FOREIGN KEY FK_8D0D8EA9CEC05175');
        $this->addSql('ALTER TABLE daily_log ADD CONSTRAINT `FK_8D0D8EA9CEC05175` FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE growth_goal DROP FOREIGN KEY FK_5EC0A4299C828A73');
        $this->addSql('ALTER TABLE growth_goal ADD CONSTRAINT `FK_5EC0A4299C828A73` FOREIGN KEY (plan_de_croissance_id) REFERENCES plan_de_croissance (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE habit DROP FOREIGN KEY FK_44FE2172A76ED395');
        $this->addSql('ALTER TABLE habit ADD CONSTRAINT `FK_44FE2172A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE hygiene_domain DROP FOREIGN KEY FK_DC9FAEA5CEC05175');
        $this->addSql('ALTER TABLE hygiene_domain ADD CONSTRAINT `FK_DC9FAEA5CEC05175` FOREIGN KEY (plan_dhygiene_id) REFERENCES plan_dhygiene (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE learning_resource DROP FOREIGN KEY FK_98E1F0087C8884D7');
        $this->addSql('ALTER TABLE learning_resource ADD CONSTRAINT `FK_98E1F0087C8884D7` FOREIGN KEY (skill_goal_id) REFERENCES skill_goal (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE legacy_goal DROP FOREIGN KEY FK_D36C5036868E2FF1');
        $this->addSql('ALTER TABLE legacy_goal ADD CONSTRAINT `FK_D36C5036868E2FF1` FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE life_goal DROP FOREIGN KEY FK_447E5690C66FEE61');
        $this->addSql('ALTER TABLE life_goal ADD CONSTRAINT `FK_447E5690C66FEE61` FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE life_milestone DROP FOREIGN KEY FK_F9A30E0EC00EDB6D');
        $this->addSql('ALTER TABLE life_milestone ADD CONSTRAINT `FK_F9A30E0EC00EDB6D` FOREIGN KEY (life_goal_id) REFERENCES life_goal (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE passion DROP FOREIGN KEY FK_7A3D8D5FC66FEE61');
        $this->addSql('ALTER TABLE passion ADD CONSTRAINT `FK_7A3D8D5FC66FEE61` FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE plan_de_croissance DROP FOREIGN KEY FK_E6F7090DA76ED395');
        $this->addSql('ALTER TABLE plan_de_croissance ADD CONSTRAINT `FK_E6F7090DA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE plan_de_developpement_personnel DROP FOREIGN KEY FK_66004F72A76ED395');
        $this->addSql('ALTER TABLE plan_de_developpement_personnel ADD CONSTRAINT `FK_66004F72A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE plan_de_transmission DROP FOREIGN KEY FK_DD2DC8E9A76ED395');
        $this->addSql('ALTER TABLE plan_de_transmission ADD CONSTRAINT `FK_DD2DC8E9A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE plan_de_vie DROP FOREIGN KEY FK_8DD8428FA76ED395');
        $this->addSql('ALTER TABLE plan_de_vie ADD CONSTRAINT `FK_8DD8428FA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE plan_dhygiene DROP FOREIGN KEY FK_4E14D138A76ED395');
        $this->addSql('ALTER TABLE plan_dhygiene ADD CONSTRAINT `FK_4E14D138A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE purpose DROP FOREIGN KEY FK_B887B3EBC66FEE61');
        $this->addSql('ALTER TABLE purpose ADD CONSTRAINT `FK_B887B3EBC66FEE61` FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE skill_goal DROP FOREIGN KEY FK_BB9D779C46D52AA2');
        $this->addSql('ALTER TABLE skill_goal ADD CONSTRAINT `FK_BB9D779C46D52AA2` FOREIGN KEY (plan_de_developpement_personnel_id) REFERENCES plan_de_developpement_personnel (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT `FK_A3C664D3A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE talent_don DROP FOREIGN KEY FK_A38961DF868E2FF1');
        $this->addSql('ALTER TABLE talent_don ADD CONSTRAINT `FK_A38961DF868E2FF1` FOREIGN KEY (plan_de_transmission_id) REFERENCES plan_de_transmission (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D19A1887DC');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `FK_723705D19A1887DC` FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE transmission_action DROP FOREIGN KEY FK_B33CE2DFB8182CE6');
        $this->addSql('ALTER TABLE transmission_action ADD CONSTRAINT `FK_B33CE2DFB8182CE6` FOREIGN KEY (legacy_goal_id) REFERENCES legacy_goal (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE `user` DROP is_verified, DROP created_at, DROP terms_accepted_at');
        $this->addSql('ALTER TABLE user_profile DROP FOREIGN KEY FK_D95AB405A76ED395');
        $this->addSql('ALTER TABLE user_profile ADD CONSTRAINT `FK_D95AB405A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE value DROP FOREIGN KEY FK_1D775834C66FEE61');
        $this->addSql('ALTER TABLE value ADD CONSTRAINT `FK_1D775834C66FEE61` FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE vision DROP FOREIGN KEY FK_8E789EA9C66FEE61');
        $this->addSql('ALTER TABLE vision ADD CONSTRAINT `FK_8E789EA9C66FEE61` FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
