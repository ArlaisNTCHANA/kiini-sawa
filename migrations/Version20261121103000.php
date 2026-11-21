<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RFC-001 : Sanctuaire du Couple 360 — streak, défis de couple et parcours de méditation à deux.
 */
final class Version20261121103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sanctuaire du Couple 360 (RFC-001) : streak_days, couple_challenge, couple_challenge_day, couple_meditation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE couple ADD streak_days INT DEFAULT 0 NOT NULL, ADD last_activity_date DATE DEFAULT NULL');

        $this->addSql('CREATE TABLE couple_challenge (
            id INT AUTO_INCREMENT NOT NULL,
            couple_id INT NOT NULL,
            challenge_code VARCHAR(60) NOT NULL,
            status VARCHAR(20) NOT NULL,
            current_day INT NOT NULL,
            total_days INT NOT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            INDEX IDX_COUPLE_CHALLENGE_COUPLE (couple_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE couple_challenge_day (
            id INT AUTO_INCREMENT NOT NULL,
            challenge_id INT NOT NULL,
            day_number INT NOT NULL,
            validated_by_partner_a TINYINT(1) NOT NULL,
            validated_by_partner_b TINYINT(1) NOT NULL,
            note_a LONGTEXT DEFAULT NULL,
            note_b LONGTEXT DEFAULT NULL,
            validated_at DATETIME DEFAULT NULL,
            INDEX IDX_COUPLE_CHALLENGE_DAY_CHALLENGE (challenge_id),
            UNIQUE INDEX uniq_couple_challenge_day (challenge_id, day_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE couple_meditation (
            id INT AUTO_INCREMENT NOT NULL,
            couple_id INT NOT NULL,
            plan_code VARCHAR(60) NOT NULL,
            status VARCHAR(20) NOT NULL,
            current_step INT NOT NULL,
            total_steps INT NOT NULL,
            started_at DATETIME NOT NULL,
            last_session_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            INDEX IDX_COUPLE_MEDITATION_COUPLE (couple_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE couple_challenge ADD CONSTRAINT FK_COUPLE_CHALLENGE_COUPLE FOREIGN KEY (couple_id) REFERENCES couple (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple_challenge_day ADD CONSTRAINT FK_COUPLE_CHALLENGE_DAY_CHALLENGE FOREIGN KEY (challenge_id) REFERENCES couple_challenge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE couple_meditation ADD CONSTRAINT FK_COUPLE_MEDITATION_COUPLE FOREIGN KEY (couple_id) REFERENCES couple (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE couple_challenge_day DROP FOREIGN KEY FK_COUPLE_CHALLENGE_DAY_CHALLENGE');
        $this->addSql('ALTER TABLE couple_challenge DROP FOREIGN KEY FK_COUPLE_CHALLENGE_COUPLE');
        $this->addSql('ALTER TABLE couple_meditation DROP FOREIGN KEY FK_COUPLE_MEDITATION_COUPLE');
        $this->addSql('DROP TABLE couple_challenge_day');
        $this->addSql('DROP TABLE couple_challenge');
        $this->addSql('DROP TABLE couple_meditation');
        $this->addSql('ALTER TABLE couple DROP streak_days, DROP last_activity_date');
    }
}
