<?php

namespace App\Texte;

use App\Chatbot\ClientClaude;
use App\Chatbot\ErreurClaude;
use App\Entity\JournalIA;
use App\Entity\Traduction;
use App\Enum\AgentIA;
use App\Repository\TraductionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Traduit en anglais les textes du site public saisis en français dans le backoffice (pages, destinations), pour un
 * visiteur qui a choisi « EN ». Un texte n'est traduit qu'une fois : la traduction est gardée en base (Traduction),
 * où l'agence peut la corriger. Les textes encore inconnus partent ensemble, en un seul appel à Claude,
 * par lots de TEXTES_PAR_APPEL.
 *
 * Si Claude ne répond pas, le texte reste en français : le site s'affiche quand même.
 */
final class Traducteur
{
    public const MODELE = 'claude-haiku-5-5';

    /**
     * Textes envoyés à Claude par requête du site ; les suivants sont traduits aux prochaines visites, et la page ne
     * fait pas attendre. Un gros lot (tout le catalogue, un millier de textes) prenait une minute et revenait avec
     * un texte de moins : tout était perdu, à chaque visite.
     */
    private const TEXTES_PAR_APPEL = 40;
    private const CARACTERES_PAR_APPEL = 8_000;

    private const CONSIGNE = <<<'TXT'
        You translate the French content of a travel agency website into natural British English for its visitors.
        You receive a JSON array of French texts and return the English translations, one per text, in the same order.
        - Some texts are HTML: keep every tag and attribute exactly as it is, translate only the text between tags.
        - Keep proper nouns, prices, figures and dates as they are; translate country and city names that have a usual English name (Italie → Italy, Venise → Venice).
        - Words between asterisks are highlighted on the site (*chercher le soleil*): keep the asterisks around the matching English words.
        - The site finds some headings by their name: translate them exactly as follows. Nos engagements → Our commitments; Séjours → Stays; Circuits → Tours; Groupes → Groups; Voyages de noces → Honeymoons; Entreprise → Corporate; Modèle à valider par l'agence → Template to be approved by the agency.
        - Keep the tone of the agency: warm, clear, never pushy.
        - Return only the translations, nothing else.
        TXT;

    public function __construct(
        private readonly ClientClaude $claude,
        private readonly TraductionRepository $traductions,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $textes textes français
     *
     * @return array<string, string> texte anglais par texte français ; un texte non traduit est absent
     */
    public function traduire(array $textes): array
    {
        $parEmpreinte = [];
        foreach ($textes as $texte) {
            if ('' !== trim($texte)) {
                $parEmpreinte[Traduction::empreinte($texte)] = $texte;
            }
        }

        $connues = $this->traductions->anglaisParEmpreinte(array_keys($parEmpreinte));
        $resultat = [];
        $aTraduire = [];
        $taille = 0;
        foreach ($parEmpreinte as $empreinte => $texte) {
            if (isset($connues[$empreinte])) {
                $resultat[$texte] = $connues[$empreinte];
            } elseif ([] === $aTraduire
                || (\count($aTraduire) < self::TEXTES_PAR_APPEL && $taille + mb_strlen($texte) <= self::CARACTERES_PAR_APPEL)) {
                $aTraduire[] = $texte;
                $taille += mb_strlen($texte);
            }
        }

        if ([] !== $aTraduire) {
            foreach ($this->demanderAClaude($aTraduire) as $francais => $anglais) {
                $this->traductions->enregistrer($francais, $anglais);
                $resultat[$francais] = $anglais;
            }
        }

        return $resultat;
    }

    /**
     * @param list<string> $textes
     *
     * @return array<string, string>
     */
    private function demanderAClaude(array $textes): array
    {
        $parametres = [
            'model' => self::MODELE,
            'max_tokens' => 32000,
            'output_config' => [
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => ['traductions' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        'required' => ['traductions'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'system' => [['type' => 'text', 'text' => self::CONSIGNE]],
            'messages' => [['role' => 'user', 'content' => json_encode($textes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)]],
        ];

        $chrono = hrtime(true);
        $journal = (new JournalIA())
            ->setAgent(AgentIA::Traduction)
            ->setModele(self::MODELE)
            ->setEntree(['textes' => $textes]);

        try {
            $flux = $this->claude->diffuser($parametres);
            foreach ($flux as $morceau) {
                // Rien à diffuser : la réponse est lue en entier.
            }
            $message = $flux->getReturn();
            $journal
                ->setModele('' !== $message->modele ? $message->modele : self::MODELE)
                ->setSortie(['contenu' => $message->contenu(), 'stopReason' => $message->stopReason])
                ->setTokensEntree($message->tokensEntree)
                ->setTokensSortie($message->tokensSortie);

            /** @var array{traductions?: mixed} $reponse */
            $reponse = json_decode($message->texte(), true, flags: \JSON_THROW_ON_ERROR);
            $traductions = $reponse['traductions'] ?? null;
            if ('end_turn' !== $message->stopReason || !\is_array($traductions) || \count($traductions) !== \count($textes)) {
                $this->logger->warning('Traduction : réponse inutilisable ({raison}).', ['raison' => $message->stopReason]);

                return [];
            }

            $resultat = [];
            foreach ($textes as $i => $francais) {
                $anglais = $traductions[$i] ?? null;
                if (\is_string($anglais) && '' !== trim($anglais)) {
                    $resultat[$francais] = $anglais;
                }
            }

            return $resultat;
        } catch (ErreurClaude|\JsonException $e) {
            $journal->setSortie(['erreur' => $e->getMessage()]);
            $this->logger->warning('Traduction : {erreur}', ['erreur' => $e->getMessage()]);

            return [];
        } finally {
            $journal->setDureeMs(intdiv(hrtime(true) - $chrono, 1_000_000));
            $this->em->persist($journal);
            $this->em->flush();
        }
    }
}
