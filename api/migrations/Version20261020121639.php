<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020121639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Combinaison choisie et fiche demande au moment du chiffrage, pour recalculer une version après une modification du client ; date d\'envoi au client, pour garder consultables les versions reçues (#211).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proposition ADD choix JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE proposition ADD exigences JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE proposition ALTER choix DROP DEFAULT');
        $this->addSql('ALTER TABLE proposition ALTER exigences DROP DEFAULT');
        $this->addSql('ALTER TABLE proposition ADD envoyee_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Versions déjà envoyées : la date de création tient lieu de date d'envoi.
        $this->addSql('UPDATE proposition SET envoyee_le = cree_le WHERE type = \'premiere\' AND statut IN (\'envoyee\', \'refusee\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proposition DROP choix');
        $this->addSql('ALTER TABLE proposition DROP exigences');
        $this->addSql('ALTER TABLE proposition DROP envoyee_le');
    }
}
