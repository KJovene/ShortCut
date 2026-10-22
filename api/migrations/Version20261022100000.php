<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Langue du client sur chaque demande (US15) : le chatbot et la proposition lui parlent en français ou en anglais.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande ADD langue VARCHAR(2) DEFAULT \'fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande DROP langue');
    }
}
