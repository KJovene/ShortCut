<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Passe la main à un conseiller (docs/outils.md). Version simple du ticket #192 : la demande passe
 * prioritaire et la conversation « reprise par un conseiller » ; le pipeline arrive avec le ticket #197.
 * Le résumé pour le conseiller est conservé dans le journal IA, avec l'entrée de l'outil.
 */
final class TransmettreAuConseiller implements Outil
{
    private const MOTIFS = ['demande_du_client', 'donnees_manquantes', 'hors_perimetre', 'autre'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function nom(): string
    {
        return 'transmettre_au_conseiller';
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Transmet la demande en cours à un conseiller de l\'agence, qui reprendra la conversation. Écris pour lui un résumé utile ; préviens ensuite le client qu\'un conseiller va le recontacter.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'motif' => ['type' => 'string', 'enum' => self::MOTIFS],
                    'resume' => ['type' => 'string', 'description' => 'Pour le conseiller : où en est la demande et ce qui bloque.'],
                ],
                'required' => ['motif', 'resume'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        if (!\in_array($entree['motif'] ?? null, self::MOTIFS, true)) {
            throw new OutilException('motif inconnu.');
        }

        $contexte->conversation->setRepriseParConseiller(true);
        $contexte->demande->setPrioritaire(true)->setModifieLe(new \DateTimeImmutable());
        $this->em->flush();

        return [
            'transmise' => true,
            'delaiAnnonce' => '2 jours ouvrés',
            'messagePourLeClient' => 'Un conseiller de l\'agence reprend votre demande et vous recontacte sous 2 jours ouvrés.',
        ];
    }
}
