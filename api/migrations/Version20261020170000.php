<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zones larges de chaque destination (« Europe », « Méditerranée », « Asie du Sud-Est »), cherchées par le chatbot.';
    }

    public function up(Schema $schema): void
    {
        // Les destinations existantes partent sans zone : à compléter dans le backoffice.
        $this->addSql('ALTER TABLE destination ADD zones JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE destination ALTER zones DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination DROP zones');
    }
}
