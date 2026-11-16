<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retrait des tests « Intelligences multiples » (8 questions) et « Architecture des talents » (6 questions) :
 * trop peu de questions pour un résultat fiable. Les résultats déjà enregistrés par les utilisateurs sont conservés.
 */
final class Version20261116160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retrait des questionnaires Intelligences multiples et Architecture des talents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM personality_answer WHERE question_id IN (SELECT id FROM personality_question WHERE test_type IN ('INTELLIGENCES', 'TALENT'))");
        $this->addSql("DELETE FROM personality_question WHERE test_type IN ('INTELLIGENCES', 'TALENT')");
        $this->addSql("DELETE FROM personality_profile WHERE test_type IN ('INTELLIGENCES', 'TALENT')");
    }

    public function down(Schema $schema): void
    {
        // Données de départ : à recréer avec une ancienne version de app:personality:seed si besoin.
    }
}
