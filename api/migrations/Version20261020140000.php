<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Durée de chaque appel IA dans le journal (US13, ticket #214).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_ia ADD duree_ms INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_ia DROP duree_ms');
    }
}
