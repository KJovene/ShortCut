<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020124417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Destination : nom affiché, accroche, description de la photo, types de voyage, meilleur mois et position sur la carte, lus par le site public.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination ADD photo_alt VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD nom VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD accroche VARCHAR(200) DEFAULT NULL');
        // Les destinations existantes partent sans type de voyage : à compléter dans le backoffice.
        $this->addSql('ALTER TABLE destination ADD types_de_voyage JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE destination ALTER types_de_voyage DROP DEFAULT');
        $this->addSql('ALTER TABLE destination ADD mois_ideal SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination DROP photo_alt');
        $this->addSql('ALTER TABLE destination DROP nom');
        $this->addSql('ALTER TABLE destination DROP accroche');
        $this->addSql('ALTER TABLE destination DROP types_de_voyage');
        $this->addSql('ALTER TABLE destination DROP mois_ideal');
        $this->addSql('ALTER TABLE destination DROP latitude');
        $this->addSql('ALTER TABLE destination DROP longitude');
    }
}
