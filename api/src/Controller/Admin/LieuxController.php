<?php

namespace App\Controller\Admin;

use App\Form\LieuType;
use App\Lieu\LieuxDuCatalogue;
use App\Lieu\RechercheDeLieux;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Résultats des menus de lieux du backoffice (LieuType), au format attendu par le widget autocomplete d'EasyAdmin :
 * `{results: [{entityId, entityAsString}], next_page}`. Une ville du monde porte aussi sa région, son pays et ses
 * coordonnées, que la fiche destination reprend (public/admin/lieux.js).
 */
#[IsGranted('ROLE_CONSEILLER')]
final class LieuxController extends AbstractController
{
    #[Route('/admin/lieux', name: 'admin_lieux', methods: ['GET'])]
    public function __invoke(Request $request, RechercheDeLieux $recherche, LieuxDuCatalogue $catalogue): JsonResponse
    {
        $lieux = $request->query->getString('lieux');
        if (!\in_array($lieux, LieuType::DISTANTS, true)) {
            throw $this->createNotFoundException();
        }
        $texte = $request->query->getString('query');

        if ('ville' === $lieux) {
            $resultats = array_map(static fn (array $v): array => [
                'entityId' => $v['ville'],
                'entityAsString' => implode(', ', array_filter([$v['ville'], $v['region'] !== $v['ville'] ? $v['region'] : null, $v['pays']])),
            ] + array_diff_key($v, ['type' => true, 'codePays' => true]), $recherche->villes($texte));
        } else {
            // Les lieux connus d'abord, puis les villes de France que la recherche trouve en plus.
            $connus = 'depart' === $lieux ? $catalogue->villesDeDepart() : [...$catalogue->villesDeDepart(), ...$catalogue->aeroports()];
            $noms = [...array_filter($connus, static fn (string $l): bool => self::contient($l, $texte)), ...array_column($recherche->villes($texte, 'FR'), 'ville')];
            $resultats = array_map(static fn (string $nom): array => ['entityId' => $nom, 'entityAsString' => $nom], array_values(array_unique($noms)));
        }

        // Une seule entrée par nom : deux villes homonymes donneraient la même valeur.
        $parNom = [];
        foreach ($resultats as $resultat) {
            $parNom[$resultat['entityId']] ??= $resultat;
        }

        return $this->json(['results' => array_values($parNom), 'next_page' => null]);
    }

    private static function contient(string $lieu, string $texte): bool
    {
        return '' === trim($texte) || str_contains(LieuxDuCatalogue::sansAccent($lieu), LieuxDuCatalogue::sansAccent(trim($texte)));
    }
}
