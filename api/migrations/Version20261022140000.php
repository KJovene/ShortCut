<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Options d\'une proposition : les activités que le chatbot propose en plus, et la date à laquelle le client y a répondu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proposition ADD options JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE proposition ADD options_choisies_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proposition DROP options');
        $this->addSql('ALTER TABLE proposition DROP options_choisies_le');
    }
}
