<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Académie, Lot 2 : inscriptions aux parcours, leçons terminées (avec l'élément créé dans un plan) et lien entre les
 * notes du Journal et les leçons.
 */
final class Version20261124050346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Académie : inscriptions, leçons terminées et notes de leçon';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE academy_completion (id INT AUTO_INCREMENT NOT NULL, response JSON DEFAULT NULL, created_entity_type VARCHAR(40) DEFAULT NULL, created_entity_id INT DEFAULT NULL, completed_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, enrollment_id INT NOT NULL, lesson_id INT NOT NULL, INDEX IDX_D8CE170B8F7DB25B (enrollment_id), INDEX IDX_D8CE170BCDF80196 (lesson_id), UNIQUE INDEX uniq_academy_completion (enrollment_id, lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE academy_enrollment (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_3BC4F794A76ED395 (user_id), INDEX IDX_3BC4F794591CC992 (course_id), UNIQUE INDEX uniq_academy_enrollment (user_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE academy_lesson_note (id INT AUTO_INCREMENT NOT NULL, plan_key VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, note_id INT NOT NULL, lesson_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D643A1E326ED0855 (note_id), INDEX IDX_D643A1E3CDF80196 (lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE academy_completion ADD CONSTRAINT FK_D8CE170B8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES academy_enrollment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_completion ADD CONSTRAINT FK_D8CE170BCDF80196 FOREIGN KEY (lesson_id) REFERENCES academy_lesson (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_enrollment ADD CONSTRAINT FK_3BC4F794A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_enrollment ADD CONSTRAINT FK_3BC4F794591CC992 FOREIGN KEY (course_id) REFERENCES academy_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_lesson_note ADD CONSTRAINT FK_D643A1E326ED0855 FOREIGN KEY (note_id) REFERENCES reflection_note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE academy_lesson_note ADD CONSTRAINT FK_D643A1E3CDF80196 FOREIGN KEY (lesson_id) REFERENCES academy_lesson (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE academy_completion DROP FOREIGN KEY FK_D8CE170B8F7DB25B');
        $this->addSql('ALTER TABLE academy_completion DROP FOREIGN KEY FK_D8CE170BCDF80196');
        $this->addSql('ALTER TABLE academy_enrollment DROP FOREIGN KEY FK_3BC4F794A76ED395');
        $this->addSql('ALTER TABLE academy_enrollment DROP FOREIGN KEY FK_3BC4F794591CC992');
        $this->addSql('ALTER TABLE academy_lesson_note DROP FOREIGN KEY FK_D643A1E326ED0855');
        $this->addSql('ALTER TABLE academy_lesson_note DROP FOREIGN KEY FK_D643A1E3CDF80196');
        $this->addSql('DROP TABLE academy_completion');
        $this->addSql('DROP TABLE academy_enrollment');
        $this->addSql('DROP TABLE academy_lesson_note');
    }
}
