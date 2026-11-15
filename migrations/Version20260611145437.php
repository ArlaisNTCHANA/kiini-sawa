<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611145437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, tier VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, trial_start_at DATETIME DEFAULT NULL, trial_end_at DATETIME DEFAULT NULL, is_trial_used TINYINT NOT NULL, current_period_start DATETIME DEFAULT NULL, current_period_end DATETIME DEFAULT NULL, cancel_at_period_end TINYINT NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, stripe_payment_method_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A3C664D3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(10) NOT NULL, status VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, stripe_invoice_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, subscription_id INT NOT NULL, INDEX IDX_723705D19A1887DC (subscription_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D19A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D19A1887DC');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE transaction');
    }
}
