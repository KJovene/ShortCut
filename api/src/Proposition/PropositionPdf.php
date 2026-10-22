<?php

namespace App\Proposition;

use App\Confirmation\PropositionClient;
use App\Entity\Proposition;
use App\Enum\Langue;
use App\Enum\NiveauBudget;
use App\Enum\TypeDeVoyage;
use App\Repository\PrestationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

/**
 * La proposition en PDF (ticket #213, US12), aux couleurs de l'agence : couverture avec le statut (estimée ou
 * confirmée), programme jour par jour, hébergements et activités avec photo, budget en trois niveaux, hypothèses.
 * Mêmes données que la page « Ma proposition » (PropositionClient::presenter) : jamais de prix d'achat.
 * Dompdf lit les photos sur le disque (public/uploads), sans accès réseau.
 */
final class PropositionPdf
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PropositionClient $client,
        private readonly PrestationRepository $prestations,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $public,
    ) {
    }

    public function generer(Proposition $proposition): string
    {
        $options = (new Options())
            ->setChroot($this->public)
            ->setIsRemoteEnabled(false)
            ->setDefaultFont('DejaVu Sans')
            ->setDefaultPaperSize('a4');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($proposition), 'UTF-8');
        $dompdf->render();

        // Numéro de page en bas à droite, sur chaque page.
        $sur = Langue::Anglais === $proposition->getDemande()?->getLangue() ? 'of' : 'sur';
        $dompdf->getCanvas()->page_text(520, 812, 'Page {PAGE_NUM} '.$sur.' {PAGE_COUNT}', 'DejaVu Sans', 7, [0.43, 0.35, 0.29]);

        return (string) $dompdf->output();
    }

    /** Le HTML du PDF, aussi vérifié par les tests (le PDF lui-même est compressé). */
    public function html(Proposition $proposition): string
    {
        $p = $this->client->presenter($proposition);
        $demande = $proposition->getDemande();
        $anglais = Langue::Anglais === $demande?->getLangue();
        $type = TypeDeVoyage::tryFrom((string) $p['demande']['typeDeVoyage']);

        $photos = [];
        $ids = array_column($p['prestations'], 'id');
        foreach ([] === $ids ? [] : $this->prestations->findBy(['id' => $ids]) as $prestation) {
            $photos[(int) $prestation->getId()] = $this->fichier('prestations', $prestation->getPhotoPrincipale());
        }

        return $this->twig->render('pdf/proposition.html.twig', [
            'p' => $p,
            'confirmee' => 'confirmee' === $p['etat'],
            'client' => $demande?->getClient()?->getPrenom() ?? $demande?->getClient()?->getNom(),
            'type' => null === $type ? null : ($anglais ? $type->labelEnAnglais() : $type->label()),
            'niveaux' => array_combine(
                array_map(static fn (NiveauBudget $n): string => $n->value, NiveauBudget::cases()),
                array_map(static fn (NiveauBudget $n): string => $anglais ? $n->labelEnAnglais() : $n->label(), NiveauBudget::cases()),
            ),
            // Les totaux par niveau, en tableau : la présentation JSON en fait un objet.
            'budgets' => $proposition->getBudgetsParNiveau(),
            'photoDestination' => $this->fichier('destinations', $proposition->getDestination()?->getPhoto()),
            'photos' => $photos,
            'logo' => $this->public.'/admin/logo-detour.svg',
        ]);
    }

    /** « proposition-santorin-v2.pdf ». */
    public function nomDeFichier(Proposition $proposition): string
    {
        $lieu = $proposition->getDestination()?->getVille() ?? $proposition->getDemande()?->getLieuOuZone() ?? 'voyage';

        return \sprintf('proposition-%s-v%d.pdf', (new AsciiSlugger('fr'))->slug($lieu)->lower(), $proposition->getVersion());
    }

    /** Chemin d'une photo enregistrée, ou null si elle manque : le PDF se génère quand même. */
    private function fichier(string $dossier, ?string $nom): ?string
    {
        if (null === $nom || '' === $nom || str_contains($nom, '/')) {
            return null;
        }
        $chemin = \sprintf('%s/uploads/%s/%s', $this->public, $dossier, $nom);

        return is_file($chemin) ? $chemin : null;
    }
}
