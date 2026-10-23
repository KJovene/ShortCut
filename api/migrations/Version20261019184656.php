<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019184656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Motif, résumé et date de transmission d\'une demande au conseiller (US06, ticket #197).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande ADD motif_transmission VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD resume_transmission TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD transmise_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande DROP motif_transmission');
        $this->addSql('ALTER TABLE demande DROP resume_transmission');
        $this->addSql('ALTER TABLE demande DROP transmise_le');
    }
}
