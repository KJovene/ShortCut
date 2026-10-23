<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Températures retirées de la demande : une préférence de climat est une envie (colonne JSON envies).';
    }

    public function up(Schema $schema): void
    {
        // Les températures déjà saisies deviennent une envie, pour ne rien perdre de ce que le client a dit.
        $this->addSql(<<<'SQL'
            UPDATE demande SET envies = (envies::jsonb || to_jsonb(CASE
                WHEN temperature_min IS NOT NULL AND temperature_max IS NOT NULL THEN 'entre ' || temperature_min || ' et ' || temperature_max || ' °C'
                WHEN temperature_min IS NOT NULL THEN 'au moins ' || temperature_min || ' °C'
                ELSE 'au plus ' || temperature_max || ' °C'
            END))::json
            WHERE temperature_min IS NOT NULL OR temperature_max IS NOT NULL
            SQL);
        $this->addSql('ALTER TABLE demande DROP temperature_min');
        $this->addSql('ALTER TABLE demande DROP temperature_max');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande ADD temperature_min INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD temperature_max INT DEFAULT NULL');
    }
}
