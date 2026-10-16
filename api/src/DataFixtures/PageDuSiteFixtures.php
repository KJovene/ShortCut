<?php

namespace App\DataFixtures;

use App\DataFixtures\Donnees\Pages;
use App\Entity\PageDuSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Textes et photos des pages du site ; données dans Donnees/Pages.php. */
final class PageDuSiteFixtures extends Fixture
{
    public function __construct(private readonly Photos $photos)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (Pages::toutes() as $p) {
            $manager->persist(
                (new PageDuSite())
                    ->setSlug($p['slug'])
                    ->setTitre($p['titre'])
                    ->setSeoTitre($p['seoTitre'])
                    ->setSeoDescription($p['seoDescription'])
                    ->setContenu($p['contenu'])
                    ->setPhotos(array_map(fn (string $photo): string => $this->photos->copier('pages', $photo), $p['photos']))
                    ->setPubliee(true)
                    ->setOrdreMenu($p['ordreMenu']),
            );
        }

        $manager->flush();
    }
}
