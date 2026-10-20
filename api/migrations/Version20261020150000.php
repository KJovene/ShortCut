<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020150000 extends AbstractMigration
{
    /** Titres du jeu de démo, avant puis après : le site affiche maintenant le titre de la page en grand. */
    private const TITRES = [
        'accueil' => ['Votre voyage sur mesure, proposé en quelques minutes', 'Où irez-vous *chercher le soleil* ?'],
        'types-de-voyages' => ['Nos types de voyages', 'Tous les voyages, *sur mesure*'],
        'agence' => ['L\'agence', 'Des conseillers qui *connaissent le terrain*'],
        'contact' => ['Contact', 'Une question ? *Parlons-en*'],
    ];

    public function getDescription(): string
    {
        return 'Titres des pages vitrines repris des maquettes, mots mis en valeur entre astérisques (#217).';
    }

    public function up(Schema $schema): void
    {
        // Un titre déjà modifié dans le backoffice est gardé.
        foreach (self::TITRES as $slug => [$avant, $apres]) {
            $this->addSql('UPDATE page_du_site SET titre = :apres WHERE slug = :slug AND titre = :avant', ['slug' => $slug, 'avant' => $avant, 'apres' => $apres]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TITRES as $slug => [$avant, $apres]) {
            $this->addSql('UPDATE page_du_site SET titre = :avant WHERE slug = :slug AND titre = :apres', ['slug' => $slug, 'avant' => $avant, 'apres' => $apres]);
        }
    }
}
