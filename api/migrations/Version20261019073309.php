<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019073309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demandes prioritaires (liste « Mes demandes » du backoffice) et frais de dossier par type de voyage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande ADD prioritaire BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX IDX_2694D7A51AC39A0D220104A7AF820231 ON demande (conseiller_id, prioritaire, cree_le)');
        // Colonne de RegleDePrix ajoutée avec le chiffrage sans migration (déjà présente si la base a été mise à jour par schema:update) ; le défaut couvre les lignes existantes.
        $this->addSql('ALTER TABLE regle_de_prix ADD COLUMN IF NOT EXISTS frais_dossier_par_type_centimes JSON DEFAULT \'{"sejour":10000,"noces":15000,"circuit":15000,"groupe":20000,"entreprise":20000}\' NOT NULL');
        $this->addSql('ALTER TABLE regle_de_prix ALTER frais_dossier_par_type_centimes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_2694D7A51AC39A0D220104A7AF820231');
        $this->addSql('ALTER TABLE demande DROP prioritaire');
        $this->addSql('ALTER TABLE regle_de_prix DROP frais_dossier_par_type_centimes');
    }
}
