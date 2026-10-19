<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Entity\Prestation;
use App\Enum\TypePrestation;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Une prestation a deux adresses possibles. Sans ce décorateur, API Platform prendrait la première
 * (/api/logements/{id}) pour toutes, y compris les activités liées depuis un tarif.
 */
#[AsDecorator('api_platform.symfony.iri_converter')]
final class IriPrestationConverter implements IriConverterInterface
{
    public function __construct(#[AutowireDecorated] private readonly IriConverterInterface $inner)
    {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->inner->getResourceFromIri($iri, $context, $operation);
    }

    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        if ($resource instanceof Prestation && null !== $resource->getId()) {
            $context['item_uri_template'] = TypePrestation::Activite === $resource->getType()
                ? Prestation::OPERATION_ACTIVITE
                : Prestation::OPERATION_LOGEMENT;
        }

        return $this->inner->getIriFromResource($resource, $referenceType, $operation, $context);
    }
}
