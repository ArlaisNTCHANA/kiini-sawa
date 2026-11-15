<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115183142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tests : suppression des anciens tests courts (MBTI, DISC, Ennéagramme, Big Five) et des noms de types copiés';
    }

    public function up(Schema $schema): void
    {
        // Remplacés par les questionnaires psychométriques ; les fiches de type copiaient des noms protégés (16Personalities)
        $this->addSql("DELETE a FROM personality_answer a JOIN personality_question q ON q.id = a.question_id WHERE q.test_type IN ('MBTI', 'DISC', 'ENNEAGRAMME', 'BIG5')");
        $this->addSql("DELETE FROM personality_question WHERE test_type IN ('MBTI', 'DISC', 'ENNEAGRAMME', 'BIG5')");
        $this->addSql("DELETE FROM personality_profile WHERE test_type IN ('MBTI', 'DISC', 'ENNEAGRAMME', 'BIG5')");

    }

    public function down(Schema $schema): void
    {
        // Données supprimées : non restaurables (relancer l'ancienne commande de seed si besoin)

    }
}
