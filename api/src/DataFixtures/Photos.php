<?php

namespace App\DataFixtures;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copie les photos libres de droits de fixtures/images vers public/uploads, où le site et le backoffice les lisent.
 * Les crédits sont dans fixtures/images/CREDITS.md.
 */
final class Photos
{
    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * @param 'destinations'|'prestations'|'pages' $dossier
     *
     * @return string nom du fichier enregistré dans l'entité (ex. « lisbonne.jpg »)
     */
    public function copier(string $dossier, string $nom): string
    {
        $fichier = $nom.'.jpg';
        $this->fs->copy(
            \sprintf('%s/fixtures/images/%s/%s', $this->projectDir, $dossier, $fichier),
            \sprintf('%s/public/uploads/%s/%s', $this->projectDir, $dossier, $fichier),
            true,
        );

        return $fichier;
    }
}
