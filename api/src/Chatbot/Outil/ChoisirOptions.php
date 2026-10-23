<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chatbot\NouvelleVersion;
use App\Chiffrage\Montant;
use App\Enum\NiveauBudget;

/**
 * Outil `choisir_options` de l'agent Conversation : la réponse du client aux activités proposées en option avec sa
 * proposition (et, s'il le dit, le niveau qu'il préfère). Il ne fait qu'enregistrer le choix : une fois la réponse du
 * modèle écrite, le serveur garde le voyage tel quel, ou le recalcule avec les options et en rédige la mise à jour
 * (NouvelleVersion::appliquerLesOptions).
 */
final class ChoisirOptions implements Outil
{
    public const NOM = 'choisir_options';

    public const INCHANGE = 'inchange';

    public function nom(): string
    {
        return self::NOM;
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Enregistre la réponse du client aux options de sa proposition (balise <proposition_preparee>) : les activités qu\'il ajoute, ou aucune s\'il garde le voyage tel quel, et le niveau qu\'il choisit s\'il en nomme un. Le serveur met ensuite sa proposition à jour.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'activitesAjoutees' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Identifiants des options que le client ajoute ; vide s\'il n\'en veut aucune.'],
                    'niveau' => ['type' => 'string', 'enum' => [...array_column(NiveauBudget::cases(), 'value'), self::INCHANGE], 'description' => 'Le niveau que le client choisit, ou « inchange » s\'il n\'en a pas parlé.'],
                ],
                'required' => ['activitesAjoutees', 'niveau'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $reference = NouvelleVersion::reference($contexte->demande)
            ?? throw new OutilException('Le client n\'a pas encore de proposition : il n\'y a pas d\'options à choisir.');
        $options = [];
        foreach ($reference->getOptions() as $option) {
            $options[$option['id']] = $option;
        }

        $ajouts = array_values(array_unique(Entree::entiers($entree, 'activitesAjoutees')));
        $inconnues = array_diff($ajouts, array_keys($options));
        if ([] !== $inconnues) {
            throw new OutilException(\sprintf('Ces identifiants ne sont pas des options de la proposition : %s. Options possibles : %s.', implode(', ', $inconnues), [] === $options ? 'aucune' : implode(', ', array_map(static fn (array $o): string => \sprintf('%d (%s)', $o['id'], $o['nom']), $options))));
        }
        $niveau = (string) ($entree['niveau'] ?? self::INCHANGE);
        $niveau = self::INCHANGE === $niveau ? null : (NiveauBudget::tryFrom($niveau) ?? throw new OutilException('niveau inconnu.'));
        if ($niveau === $reference->getNiveauBudget()) {
            $niveau = null;
        }

        $aRecalculer = [] !== $ajouts || null !== $niveau;

        return [
            'activitesAjoutees' => $ajouts,
            'niveau' => $niveau?->value,
            'ajouts' => array_map(static fn (int $id): string => \sprintf('%s (+%s)', $options[$id]['nom'], Montant::euros($options[$id]['prixCentimes'])), $ajouts),
            'consigne' => $aRecalculer
                ? 'Dis en une phrase courte que tu mets sa proposition à jour avec son choix ; les nouveaux montants s\'affichent après ton message : n\'en écris aucun.'
                : 'Dis en une phrase courte que tu gardes son voyage tel quel, sans insister ni reproposer d\'option.',
        ];
    }
}
