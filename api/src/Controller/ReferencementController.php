<?php

namespace App\Controller;

use App\Repository\DestinationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan du site et robots.txt du site React, servis par l'API (proxifiés par Vite en local) : le plan suit
 * les destinations publiées dans le backoffice sans redéploiement du site.
 */
final class ReferencementController
{
    /** Pages publiques à indexer ; le compte, l'espace client et les propositions personnelles n'y sont pas. */
    private const PAGES = ['/', '/destinations', '/types-de-voyages', '/agence', '/contact', '/mentions-legales', '/cgv', '/confidentialite'];

    public function __construct(#[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl)
    {
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(DestinationRepository $destinations): Response
    {
        $chemins = self::PAGES;
        foreach ($destinations->findBy(['publiee' => true], ['slug' => 'ASC']) as $destination) {
            $chemins[] = '/destinations/'.$destination->getSlug();
        }

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($chemins as $chemin) {
            $xml->startElement('url');
            $xml->writeElement('loc', $this->url($chemin));
            $xml->endElement();
        }
        $xml->endElement();

        return new Response($xml->outputMemory(), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        $lignes = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /espace',
            'Disallow: /profil',
            'Disallow: /proposition/',
            '',
            'Sitemap: '.$this->url('/sitemap.xml'),
        ];

        return new Response(implode("\n", $lignes)."\n", headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function url(string $chemin): string
    {
        return rtrim($this->siteUrl, '/').$chemin;
    }
}
