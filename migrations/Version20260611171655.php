<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611171655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_config (id VARCHAR(50) NOT NULL, pro_monthly_price DOUBLE PRECISION DEFAULT 9.99 NOT NULL, pro_yearly_price DOUBLE PRECISION DEFAULT 79.99 NOT NULL, trial_duration_days INT DEFAULT 14 NOT NULL, max_growth_goals_free INT DEFAULT 2 NOT NULL, max_habits_free INT DEFAULT 3 NOT NULL, max_connections_free INT DEFAULT 1 NOT NULL, max_tasks_per_day_free INT DEFAULT 10 NOT NULL, max_values_free INT DEFAULT 3 NOT NULL, max_goals_free INT DEFAULT 5 NOT NULL, max_challenges_free INT DEFAULT 1 NOT NULL, enable_print_free TINYINT DEFAULT 0 NOT NULL, enable_map_free TINYINT DEFAULT 0 NOT NULL, enable_analytics_free TINYINT DEFAULT 0 NOT NULL, enable_monthly_reviews_free TINYINT DEFAULT 0 NOT NULL, enable_plan_history_free TINYINT DEFAULT 0 NOT NULL, enable_month_year_view_free TINYINT DEFAULT 0 NOT NULL, enable_advanced_export_free TINYINT DEFAULT 0 NOT NULL, enable_hygiene_detailed_free TINYINT DEFAULT 0 NOT NULL, enable_coherence_analysis_free TINYINT DEFAULT 0 NOT NULL, enable_advanced_comments_free TINYINT DEFAULT 0 NOT NULL, enable_challenges_free TINYINT DEFAULT 0 NOT NULL, enable_discovery_free TINYINT DEFAULT 0 NOT NULL, enable_journal_free TINYINT DEFAULT 0 NOT NULL, enable_growth_free TINYINT DEFAULT 0 NOT NULL, enable_hygiene_free TINYINT DEFAULT 0 NOT NULL, enable_contribution_free TINYINT DEFAULT 0 NOT NULL, enable_planner_free TINYINT DEFAULT 0 NOT NULL, enable_habits_free TINYINT DEFAULT 0 NOT NULL, enable_social_free TINYINT DEFAULT 0 NOT NULL, enable_coaching_free TINYINT DEFAULT 0 NOT NULL, enable_method_free TINYINT DEFAULT 1 NOT NULL, demo_password VARCHAR(255) DEFAULT \'password123\' NOT NULL, resend_from_email VARCHAR(255) DEFAULT NULL, resend_from_name VARCHAR(255) DEFAULT NULL, resend_api_key VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL, html_body LONGTEXT NOT NULL, text_body LONGTEXT DEFAULT NULL, enabled TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9C0600CA8CDE5729 (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quote (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, author VARCHAR(255) DEFAULT NULL, category VARCHAR(100) DEFAULT \'MOTIVATION\' NOT NULL, display_date DATETIME DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_config');
        $this->addSql('DROP TABLE email_template');
        $this->addSql('DROP TABLE quote');
    }
}
