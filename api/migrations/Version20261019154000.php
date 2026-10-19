<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marque les tarifs saisis depuis le panneau admin pour ne pas exposer les fixtures au front.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_par_periode ADD saisi_depuis_admin BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_par_periode DROP saisi_depuis_admin');
    }
}
