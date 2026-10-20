<?php

namespace App\ApiPlatform;

use App\Chiffrage\PrixAPartirDe;
use App\Entity\Destination;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Ajoute à une destination du site public (groupe `public:read`) ses prix « à partir de » (PrixAPartirDe) : par
 * personne, et sur quelques exemples de logements et d'activités. Ce sont des prix de vente : les tarifs d'achat et
 * les prestataires ne sortent jamais.
 */
final class DestinationPubliqueNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const DEJA_APPELE = 'destination_publique_normalizer';

    public function __construct(private readonly PrixAPartirDe $prix)
    {
    }

    /**
     * @param Destination $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $context[self::DEJA_APPELE] = true;

        /** @var array<string, mixed> $normalisee */
        $normalisee = $this->normalizer->normalize($data, $format, $context);
        $prix = $this->prix->calculer($data);

        $normalisee['prixAPartirDeParPersonneEuros'] = $prix['parPersonneEuros'];
        $normalisee['exemples'] = [];
        foreach ($prix['exemples'] as $exemple) {
            /** @var array<string, mixed> $normalise */
            $normalise = $this->normalizer->normalize($exemple['prestation'], 'json', ['groups' => ['public:read']]);
            $normalisee['exemples'][] = $normalise + ['prixAPartirDeEuros' => $exemple['euros'], 'unite' => $exemple['unite']->value];
        }

        return $normalisee;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Destination
            && !isset($context[self::DEJA_APPELE])
            && \in_array('public:read', (array) ($context['groups'] ?? []), true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Destination::class => false];
    }
}
