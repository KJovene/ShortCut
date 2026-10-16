<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016122123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Frais de dossier par type de voyage dans les règles de prix (décision #165)';
    }

    public function up(Schema $schema): void
    {
        // Barème décidé avec l'agence, appliqué aux règles de prix déjà enregistrées.
        $this->addSql('ALTER TABLE regle_de_prix ADD frais_dossier_par_type_centimes JSON DEFAULT \'{"sejour": 10000, "noces": 15000, "circuit": 15000, "groupe": 20000, "entreprise": 20000}\' NOT NULL');
        $this->addSql('ALTER TABLE regle_de_prix ALTER frais_dossier_par_type_centimes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE regle_de_prix DROP frais_dossier_par_type_centimes');
    }
}
