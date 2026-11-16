<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme la table « value » en « life_value » : « value » est un mot réservé de MariaDB
 * (Hostinger), où « INSERT INTO value (…) » provoque une erreur de syntaxe. Les données sont conservées.
 */
final class Version20261116140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table value renommée en life_value (compatibilité MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `value` DROP FOREIGN KEY FK_1D775834C66FEE61');
        $this->addSql('RENAME TABLE `value` TO life_value');
        $this->addSql('ALTER TABLE life_value RENAME INDEX IDX_1D775834C66FEE61 TO IDX_31166402C66FEE61');
        $this->addSql('ALTER TABLE life_value ADD CONSTRAINT FK_31166402C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE life_value DROP FOREIGN KEY FK_31166402C66FEE61');
        $this->addSql('RENAME TABLE life_value TO `value`');
        $this->addSql('ALTER TABLE `value` RENAME INDEX IDX_31166402C66FEE61 TO IDX_1D775834C66FEE61');
        $this->addSql('ALTER TABLE `value` ADD CONSTRAINT FK_1D775834C66FEE61 FOREIGN KEY (plan_de_vie_id) REFERENCES plan_de_vie (id) ON DELETE CASCADE');
    }
}
