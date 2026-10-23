<?php

namespace App\Chiffrage;

use App\Entity\Demande;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Garde chaque chiffrage 24 heures sous sa demande, avec son identifiant (« chf_7f3a2c ») : enregistrer_proposition
 * le retrouve par cet identifiant. Un identifiant d'une autre demande est inconnu (docs/outils.md).
 */
final class ChiffrageCache
{
    private const DUREE_SECONDES = 86400;

    public function __construct(private readonly CacheItemPoolInterface $cacheApp)
    {
    }

    /**
     * @param array<string, mixed> $chiffrage
     * @param string|null          $identifiant pour compléter un chiffrage déjà gardé (retenir_combinaison y ajoute
     *                                          la combinaison et sa justification) ; un nouvel identifiant sinon
     *
     * @return string l'identifiant du chiffrage
     */
    public function enregistrer(Demande $demande, array $chiffrage, ?string $identifiant = null): string
    {
        $identifiant ??= 'chf_'.bin2hex(random_bytes(4));
        $element = $this->cacheApp->getItem($this->cle($demande, $identifiant));
        $this->cacheApp->save($element->set($chiffrage)->expiresAfter(self::DUREE_SECONDES));

        return $identifiant;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrouver(Demande $demande, string $identifiant): ?array
    {
        if (!preg_match('/^chf_[0-9a-f]{8}$/', $identifiant)) {
            return null;
        }
        $element = $this->cacheApp->getItem($this->cle($demande, $identifiant));

        return $element->isHit() && \is_array($element->get()) ? $element->get() : null;
    }

    private function cle(Demande $demande, string $identifiant): string
    {
        return \sprintf('chiffrage.%d.%s', $demande->getId() ?? throw new \LogicException('La demande doit être enregistrée.'), $identifiant);
    }
}
