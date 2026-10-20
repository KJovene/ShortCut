<?php

namespace App\Proposition;

use App\Enum\NiveauBudget;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ce que l'agent Rédaction écrit pour une proposition (entrée de l'outil enregistrer_proposition), dénormalisé par
 * le Serializer et validé par le Validator avant enregistrement. Les montants n'y figurent pas : ils viennent du
 * chiffrage en cache. Le lien avec la demande (durée, prestations retenues) est vérifié par PropositionService.
 */
final class PropositionRedigee
{
    /**
     * @param list<Argument>        $argumentaire
     * @param list<PointsForts>     $prestations
     * @param list<JourDuProgramme> $programme
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $chiffrageId,
        public readonly NiveauBudget $niveauDetaille,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1500)]
        public readonly string $pourquoi,
        #[Assert\Count(min: 1, max: 10, minMessage: 'Relie au moins une exigence du client à la proposition.')]
        #[Assert\Valid]
        public readonly array $argumentaire,
        #[Assert\Count(min: 1, minMessage: 'Présente au moins un logement.')]
        #[Assert\Valid]
        public readonly array $prestations,
        #[Assert\Count(min: 2, max: 61)]
        #[Assert\Valid]
        public readonly array $programme,
        #[Assert\Length(max: 1500)]
        public readonly ?string $resumeDesChangements = null,
    ) {
    }

    /** Les jours se suivent à partir du jour 1, sans trou ni doublon. */
    #[Assert\Callback]
    public function verifierLaNumerotation(ExecutionContextInterface $contexte): void
    {
        foreach ($this->programme as $index => $jour) {
            if ($jour->jour !== $index + 1) {
                $contexte->buildViolation(\sprintf('Les jours doivent être numérotés 1, 2, 3… dans l\'ordre : le jour en position %d porte le numéro %d.', $index + 1, $jour->jour))
                    ->atPath(\sprintf('programme[%d].jour', $index))
                    ->addViolation();

                return;
            }
        }
    }
}
