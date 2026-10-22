<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page « Destinations » dans les contenus du site : titre, introduction et référencement du catalogue, et sa place dans le menu.';
    }

    public function up(Schema $schema): void
    {
        // En tête du menu, comme avant : elle y était fixée par le site.
        $this->addSql(
            'INSERT INTO page_du_site (titre, slug, contenu, photos, seo_titre, seo_description, publiee, ordre_menu) VALUES (:titre, :slug, :contenu, :photos, :seoTitre, :seoDescription, true, 1) ON CONFLICT (slug) DO NOTHING',
            [
                'titre' => 'Où partir, *et quand* ?',
                'slug' => 'destinations',
                'contenu' => '<p>Choisissez un mois et une température : nous vous montrons les destinations dont le climat convient, d\'après les relevés saisis par nos conseillers.</p>',
                'photos' => '[]',
                'seoTitre' => 'Destinations · Où partir, et quand ?',
                'seoDescription' => 'Toutes nos destinations, filtrables par mois, température moyenne et type de voyage.',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM page_du_site WHERE slug = \'destinations\'');
    }
}
