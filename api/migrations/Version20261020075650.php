<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020075650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposition rédigée par l\'agent : destination, argumentaire, points forts des prestations, détail du calcul des lignes (#207).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_de_prestation ADD calcul VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE proposition ADD pourquoi TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE proposition ADD argumentaire JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE proposition ADD prestations JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE proposition ALTER argumentaire DROP DEFAULT');
        $this->addSql('ALTER TABLE proposition ALTER prestations DROP DEFAULT');
        $this->addSql('ALTER TABLE proposition ADD destination_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE proposition ADD CONSTRAINT FK_C7CDC353816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C7CDC353816C6140 ON proposition (destination_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_de_prestation DROP calcul');
        $this->addSql('ALTER TABLE proposition DROP CONSTRAINT FK_C7CDC353816C6140');
        $this->addSql('DROP INDEX IDX_C7CDC353816C6140');
        $this->addSql('ALTER TABLE proposition DROP pourquoi');
        $this->addSql('ALTER TABLE proposition DROP argumentaire');
        $this->addSql('ALTER TABLE proposition DROP prestations');
        $this->addSql('ALTER TABLE proposition DROP destination_id');
    }
}
