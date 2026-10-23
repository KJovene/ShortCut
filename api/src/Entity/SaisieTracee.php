<?php

namespace App\Entity;

/** Donnée du catalogue dont on garde qui l'a saisie ou modifiée en dernier, et quand (TraceDeSaisieListener). */
interface SaisieTracee
{
    public function setSaisiPar(?Utilisateur $saisiPar): static;

    public function setModifieLe(\DateTimeImmutable $modifieLe): static;
}
