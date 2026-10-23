<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Montant;
use App\Enum\NiveauBudget;
use App\Proposition\PropositionRefusee;
use App\Proposition\PropositionService;

/**
 * Outil `enregistrer_proposition` de l'agent Rédaction (ticket #207, docs/outils.md) : le programme jour par jour,
 * les points forts des prestations et l'argumentaire rédigés par le modèle, sur un chiffrage déjà retenu. Le serveur
 * valide la rédaction (Serializer, Validator, cohérence avec le chiffrage) avant d'enregistrer la version en brouillon.
 */
final class EnregistrerProposition implements Outil
{
    public const NOM = 'enregistrer_proposition';

    public function __construct(private readonly PropositionService $propositions)
    {
    }

    public function nom(): string
    {
        return self::NOM;
    }

    public function definition(): array
    {
        $texte = ['type' => 'string'];

        return [
            'name' => $this->nom(),
            'description' => 'Enregistre la proposition de la demande en cours, que le conseiller relira avant de l\'envoyer au client, à partir du chiffrage retenu : programme jour par jour, points forts de chaque logement et activité, argumentaire. Les prix viennent du chiffrage : n\'en écris aucun. Si la proposition est refusée, corrige chaque point signalé et rappelle l\'outil.',
            // Outil qui écrit : mode strict, voir docs/outils.md « Schémas ».
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'chiffrageId' => $texte + ['description' => 'Identifiant du chiffrage donné dans le premier message.'],
                    'niveauDetaille' => ['type' => 'string', 'enum' => array_column(NiveauBudget::cases(), 'value'), 'description' => 'Niveau que décrit le programme : celui conseillé dans le premier message.'],
                    'pourquoi' => $texte + ['description' => 'Deux ou trois phrases au client : pourquoi ce voyage lui correspond.'],
                    'argumentaire' => [
                        'type' => 'array',
                        'description' => 'Chaque exigence importante du client et ce qui, dans la proposition, y répond.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'exigence' => $texte + ['description' => 'Ce que le client a demandé, avec ses mots (« de la chaleur en février »).'],
                                'reponse' => $texte + ['description' => 'Le fait de la proposition qui y répond (« 21 °C de moyenne à Tenerife en février »).'],
                            ],
                            'required' => ['exigence', 'reponse'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'prestations' => [
                        'type' => 'array',
                        'description' => 'Une entrée par logement et par activité retenus par au moins un niveau, et elles seules.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'pointsForts' => ['type' => 'array', 'items' => $texte, 'description' => 'Deux ou trois points forts courts, tirés de la fiche de la prestation.'],
                            ],
                            'required' => ['id', 'pointsForts'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'programme' => [
                        'type' => 'array',
                        'description' => 'Un jour par jour de voyage, du jour d\'arrivée (jour 1) au jour du départ, numérotés dans l\'ordre.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'jour' => ['type' => 'integer'],
                                'titre' => $texte,
                                'description' => $texte,
                                'activiteIds' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Activités du niveau détaillé prévues ce jour-là.'],
                            ],
                            'required' => ['jour', 'titre', 'description', 'activiteIds'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'resumeDesChangements' => $texte + ['description' => 'Pour une nouvelle version : ce qui a changé par rapport à la précédente.'],
                ],
                'required' => ['chiffrageId', 'niveauDetaille', 'pourquoi', 'argumentaire', 'prestations', 'programme'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        try {
            $proposition = $this->propositions->enregistrer($contexte->demande, $this->propositions->lire($entree));
        } catch (PropositionRefusee $e) {
            throw new OutilException($e->getMessage(), previous: $e);
        }

        // Brouillon : le conseiller le relit et l'envoie au client depuis le backoffice.
        return [
            'version' => $proposition->getVersion(),
            'statut' => $proposition->getStatut()->value,
            'nombreDeJours' => \count($proposition->getProgramme()),
            'niveauDetaille' => $proposition->getNiveauBudget()->value,
            'totalEuros' => array_map(static fn (array $n): int|float => Montant::nombre($n['totalCentimes']), $proposition->getBudgetsParNiveau()),
        ];
    }
}
