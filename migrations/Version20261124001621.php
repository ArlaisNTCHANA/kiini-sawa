<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124001621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Académie : parcours, modules, leçons et questions de discernement (Lot 1, studio auteur)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE academy_course (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, title VARCHAR(160) NOT NULL, subtitle VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, primary_plan VARCHAR(20) DEFAULT NULL, is_couple TINYINT NOT NULL, access_level VARCHAR(10) NOT NULL, status VARCHAR(10) NOT NULL, sort_order INT NOT NULL, first_published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7B9610ED989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE academy_lesson (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, title VARCHAR(160) NOT NULL, sort_order INT NOT NULL, visible TINYINT NOT NULL, content_markdown LONGTEXT NOT NULL, media_provider VARCHAR(10) NOT NULL, media_ref VARCHAR(60) DEFAULT NULL, duration_minutes INT NOT NULL, practice_prompt LONGTEXT DEFAULT NULL, target_plan VARCHAR(20) DEFAULT NULL, target_action VARCHAR(40) DEFAULT NULL, updated_at DATETIME NOT NULL, module_id INT NOT NULL, INDEX IDX_957C0BA7AFC2B591 (module_id), UNIQUE INDEX uniq_academy_lesson_module_slug (module_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE academy_module (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, sort_order INT NOT NULL, course_id INT NOT NULL, INDEX IDX_612C597C591CC992 (course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE academy_question (id INT AUTO_INCREMENT NOT NULL, sort_order INT NOT NULL, question LONGTEXT NOT NULL, options JSON NOT NULL, correct_option_ids JSON NOT NULL, explanation LONGTEXT NOT NULL, lesson_id INT NOT NULL, INDEX IDX_A89AD0BBCDF80196 (lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE academy_lesson ADD CONSTRAINT FK_957C0BA7AFC2B591 FOREIGN KEY (module_id) REFERENCES academy_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_module ADD CONSTRAINT FK_612C597C591CC992 FOREIGN KEY (course_id) REFERENCES academy_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_question ADD CONSTRAINT FK_A89AD0BBCDF80196 FOREIGN KEY (lesson_id) REFERENCES academy_lesson (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE academy_lesson DROP FOREIGN KEY FK_957C0BA7AFC2B591');
        $this->addSql('ALTER TABLE academy_module DROP FOREIGN KEY FK_612C597C591CC992');
        $this->addSql('ALTER TABLE academy_question DROP FOREIGN KEY FK_A89AD0BBCDF80196');
        $this->addSql('DROP TABLE academy_course');
        $this->addSql('DROP TABLE academy_lesson');
        $this->addSql('DROP TABLE academy_module');
        $this->addSql('DROP TABLE academy_question');
    }
}
