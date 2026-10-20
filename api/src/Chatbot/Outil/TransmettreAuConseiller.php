<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Enum\MotifTransmission;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Passe la main à un conseiller (docs/outils.md, US06) : la demande devient prioritaire et arrive dans « À aiguiller »,
 * avec le motif et le résumé affichés sur sa fiche. La conversation est reprise par un conseiller : le chatbot
 * ne répond plus au client (AgentConversation).
 */
final class TransmettreAuConseiller implements Outil
{
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
                    'motif' => ['type' => 'string', 'enum' => array_map(static fn (MotifTransmission $m) => $m->value, MotifTransmission::cases())],
                    'resume' => ['type' => 'string', 'description' => 'Pour le conseiller : où en est la demande et ce qui bloque.'],
                ],
                'required' => ['motif', 'resume'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $motif = MotifTransmission::tryFrom((string) ($entree['motif'] ?? '')) ?? throw new OutilException('motif inconnu.');
        $resume = trim((string) ($entree['resume'] ?? ''));
        if ('' === $resume) {
            throw new OutilException('resume ne doit pas être vide : le conseiller en a besoin.');
        }

        $contexte->conversation->setRepriseParConseiller(true);
        $contexte->demande->transmettre($motif, $resume);
        $this->em->flush();

        return [
            'transmise' => true,
            'delaiAnnonce' => '2 jours ouvrés',
            'messagePourLeClient' => 'Un conseiller de l\'agence reprend votre demande et vous recontacte sous 2 jours ouvrés.',
        ];
    }
}
