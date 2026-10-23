<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Model\FileUploadState;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Contournement d'EasyAdmin 5.7, pour les champs photos multiples (pages du site, logements, activités) :
 * supprimer une photo et en ajouter une dans le même enregistrement plantait. Les nouvelles photos sont
 * numérotées à la suite des photos d'avant la suppression ; on retire donc les photos supprimées avant
 * l'enregistrement des fichiers.
 */
trait TeleversementsMultiples
{
    /**
     * @param FormInterface<mixed> $form
     */
    protected function processUploadedFiles(FormInterface $form): void
    {
        foreach ($form as $champ) {
            $state = $champ->getConfig()->getAttribute('state');
            if (!$champ->getConfig()->getType()->getInnerType() instanceof FileUploadType || !$state instanceof FileUploadState || [] === $state->getDeletedFiles()) {
                continue;
            }
            $state->setCurrentFiles(array_values(array_filter(
                $state->getCurrentFiles(),
                static fn (File $photo): bool => !\in_array($photo->getFilename(), $state->getDeletedFiles(), true),
            )));
        }

        parent::processUploadedFiles($form);
    }
}
