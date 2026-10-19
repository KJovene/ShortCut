<?php

namespace App\DataFixtures;

use App\Entity\RegleDePrix;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de démo (mot de passe commun : « shortcut ») et règles de prix de l'agence.
 */
final class UtilisateurFixtures extends Fixture
{
    public const MOT_DE_PASSE = 'shortcut';
    public const LEA = 'utilisateur-lea';
    public const HUGO = 'utilisateur-hugo';
    public const CLIENT = 'utilisateur-client';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $comptes = [
            'utilisateur-admin' => ['admin@shortcut.test', 'Claire Martin', Role::Administrateur],
            self::LEA => ['lea@shortcut.test', 'Léa Dubois', Role::Conseiller],
            self::HUGO => ['hugo@shortcut.test', 'Hugo Bernard', Role::Conseiller],
            self::CLIENT => ['client@shortcut.test', 'Camille Durand', Role::Client],
        ];

        foreach ($comptes as $reference => [$email, $nom, $role]) {
            $utilisateur = (new Utilisateur())->setEmail($email)->setNom($nom)->setRole($role);
            $utilisateur->setMotDePasse($this->hasher->hashPassword($utilisateur, self::MOT_DE_PASSE));
            $manager->persist($utilisateur);
            $this->addReference($reference, $utilisateur);
        }

        // Valeurs par défaut de l'entité : marge 20 %, frais de dossier de 100 à 200 €.
        $manager->persist(new RegleDePrix());

        $manager->flush();
    }
}
