<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accueil : titre du thème « voyages froids », modifiable dans les contenus du site.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_du_site ADD titre_froid VARCHAR(150) DEFAULT NULL');
        // Accueil existante : l'accroche des maquettes, que l'agence pourra ensuite modifier.
        $this->addSql("UPDATE page_du_site SET titre_froid = 'Où irez-vous *chercher la fraîcheur* ?' WHERE slug = 'accueil'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_du_site DROP titre_froid');
    }
}
