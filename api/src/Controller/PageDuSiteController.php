<?php

namespace App\Controller;

use App\Repository\PageDuSiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contenus des pages du site (accueil, types de voyages, agence, contact…), modifiables dans le backoffice.
 * Route publique : seules les pages publiées sont servies, une page masquée répond 404 comme une page inconnue.
 */
final class PageDuSiteController extends AbstractController
{
    #[Route('/api/pages/{slug}', name: 'api_page_du_site', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function page(string $slug, PageDuSiteRepository $pages): JsonResponse
    {
        $page = $pages->findOneBy(['slug' => $slug, 'publiee' => true]);
        if (null === $page) {
            return $this->json(['erreur' => 'Page introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json([
            'slug' => $page->getSlug(),
            'titre' => $page->getTitre(),
            'contenu' => $page->getContenu() ?? '',
            'photos' => array_map(static fn (string $photo): string => '/uploads/pages/'.$photo, $page->getPhotos()),
            'seoTitre' => $page->getSeoTitre(),
            'seoDescription' => $page->getSeoDescription(),
        ]);
    }
}
