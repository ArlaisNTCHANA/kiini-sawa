<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Point 9 du parcours nouvel utilisateur (26/11/2026) : Équilibre de Vie, la question de la spiritualité nomme Dieu
 * (application chrétienne assumée) au lieu de « quelque chose de plus grand ou votre philosophie de vie ».
 */
final class Version20261126150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Équilibre de Vie : la question et le profil Spiritualité nomment Dieu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE personality_question SET question = 'Sur une échelle de 1 à 10, comment évaluez-vous votre relation avec Dieu et votre vie spirituelle ?' WHERE test_type = 'EQUILIBRE' AND category = 'spiritualite'");
        $this->addSql("UPDATE personality_profile SET description = 'Votre relation avec Dieu et votre alignement intérieur sont vos guides dominants.' WHERE test_type = 'EQUILIBRE' AND code = 'SPIRITUALITE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE personality_question SET question = 'Sur une échelle de 1 à 10, comment évaluez-vous votre connexion à quelque chose de plus grand ou à votre philosophie de vie ?' WHERE test_type = 'EQUILIBRE' AND category = 'spiritualite'");
        $this->addSql("UPDATE personality_profile SET description = 'Votre connexion à un sens plus grand et votre alignement intérieur sont vos guides dominants.' WHERE test_type = 'EQUILIBRE' AND code = 'SPIRITUALITE'");
    }
}
