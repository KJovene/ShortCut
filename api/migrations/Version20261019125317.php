<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019125317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique de la conversation au format de l\'API Claude (route de chat en streaming, ticket #192).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation ADD historique TEXT DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation DROP historique');
    }
}
