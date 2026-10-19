<?php

namespace App\Agent;

/**
 * Entrée d'outil refusée. Le message, en français, est renvoyé au modèle tel quel pour qu'il corrige son appel
 * ou l'explique au client : {"erreur": "La destination « paris » n'existe pas dans le catalogue."}.
 */
final class EntreeInvalide extends \InvalidArgumentException
{
}
