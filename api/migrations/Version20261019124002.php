<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019124002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nombre de chambres des logements (#188)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestation ADD chambres INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestation DROP chambres');
    }
}
