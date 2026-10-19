<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TarifParPeriode;
use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Trace qui a saisi ou modifié un tarif, et quand, avant de l'enregistrer.
 *
 * @implements ProcessorInterface<TarifParPeriode, TarifParPeriode>
 */
final class SaisieTarifProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<TarifParPeriode, TarifParPeriode> $persist
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persist,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TarifParPeriode
    {
        $utilisateur = $this->security->getUser();
        $data
            ->setSaisiPar($utilisateur instanceof Utilisateur ? $utilisateur : null)
            ->setModifieLe(new \DateTimeImmutable());

        return $this->persist->process($data, $operation, $uriVariables, $context);
    }
}
