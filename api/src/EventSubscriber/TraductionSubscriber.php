<?php

namespace App\EventSubscriber;

use App\Texte\Traducteur;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Site public en anglais : quand le visiteur a choisi « EN », le site lit les pages et les destinations avec
 * `?langue=en`. Les textes saisis en français dans le backoffice sont alors remplacés par leur traduction
 * (Traducteur) ; les slugs, codes, prix et dates restent tels quels.
 */
final class TraductionSubscriber implements EventSubscriberInterface
{
    /** Routes du site public dont les textes viennent du backoffice. */
    private const CHEMINS = ['/api/pages/', '/api/public/'];

    /** Champs traduits, à toute profondeur de la réponse (une destination et ses exemples, une page). */
    private const CHAMPS = [
        'titre', 'contenu', 'seoTitre', 'seoDescription',
        'nom', 'ville', 'pays', 'region', 'description', 'accroche', 'pointsForts', 'photoAlt', 'categorie',
    ];

    public function __construct(private readonly Traducteur $traducteur)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $chemin = $request->getPathInfo();
        if (!$event->isMainRequest()
            || !$request->isMethod('GET')
            || 'en' !== $request->query->get('langue')
            || 200 !== $response->getStatusCode()
            || [] === array_filter(self::CHEMINS, static fn (string $prefixe) => str_starts_with($chemin, $prefixe))
        ) {
            return;
        }

        try {
            $donnees = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }
        if (!\is_array($donnees)) {
            return;
        }

        $textes = [];
        self::collecter($donnees, $textes);
        if ([] === $textes) {
            return;
        }

        $traductions = $this->traducteur->traduire(array_values(array_unique($textes)));
        $response->setContent(json_encode(
            self::remplacer($donnees, $traductions),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    /**
     * @param array<mixed> $donnees
     * @param list<string> $textes
     */
    private static function collecter(array $donnees, array &$textes, bool $aTraduire = false): void
    {
        foreach ($donnees as $cle => $valeur) {
            $champ = $aTraduire || (\is_string($cle) && \in_array($cle, self::CHAMPS, true));
            if (\is_string($valeur) && $champ) {
                $textes[] = $valeur;
            } elseif (\is_array($valeur)) {
                // Liste de textes (pointsForts) : chaque élément est traduit ; ailleurs on descend à la recherche des champs.
                self::collecter($valeur, $textes, $champ && array_is_list($valeur));
            }
        }
    }

    /**
     * @param array<mixed>          $donnees
     * @param array<string, string> $traductions
     *
     * @return array<mixed>
     */
    private static function remplacer(array $donnees, array $traductions, bool $aTraduire = false): array
    {
        foreach ($donnees as $cle => $valeur) {
            $champ = $aTraduire || (\is_string($cle) && \in_array($cle, self::CHAMPS, true));
            if (\is_string($valeur) && $champ) {
                $donnees[$cle] = $traductions[$valeur] ?? $valeur;
            } elseif (\is_array($valeur)) {
                $donnees[$cle] = self::remplacer($valeur, $traductions, $champ && array_is_list($valeur));
            }
        }

        return $donnees;
    }
}
