<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Compte de l'utilisateur depuis le site React : inscription (rôle client), connexion (json_login),
 * profil et déconnexion. La session Symfony ouvre ensuite le backoffice aux conseillers et à l'administrateur.
 *
 * Les erreurs de saisie sont renvoyées en 422 : {"erreurs": {"champ": "message"}}.
 */
#[Route('/api')]
final class SecuriteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    /** Un mauvais mot de passe est refusé par le pare-feu (401 « Identifiants invalides. ») avant ce contrôleur. */
    #[Route('/connexion', name: 'api_connexion', methods: ['POST'])]
    public function connexion(#[CurrentUser] ?Utilisateur $utilisateur): JsonResponse
    {
        // Requête qui n'est pas du JSON : json_login ne s'est pas déclenché.
        if (null === $utilisateur) {
            return $this->json(['error' => 'Saisissez votre e-mail et votre mot de passe.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->profil($utilisateur);
    }

    /** Inscription libre sur le site : le compte est toujours client, puis connecté. */
    #[Route('/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(Request $request): JsonResponse
    {
        $donnees = $request->toArray();
        $utilisateur = (new Utilisateur())->setRole(Role::Client);
        $this->appliquerCoordonnees($utilisateur, $donnees);

        $motDePasse = \is_string($donnees['motDePasse'] ?? null) ? $donnees['motDePasse'] : '';
        $erreurs = $this->erreurs($this->validator->validate($utilisateur))
            + $this->erreurs($this->validator->validate($motDePasse, self::contraintesMotDePasse()), 'motDePasse');
        if ([] !== $erreurs) {
            return $this->json(['erreurs' => $erreurs], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $utilisateur->setMotDePasse($this->hasher->hashPassword($utilisateur, $motDePasse));
        $this->em->persist($utilisateur);
        $this->em->flush();
        $this->security->login($utilisateur, 'json_login', 'main');

        return $this->profil($utilisateur, Response::HTTP_CREATED);
    }

    /** Utilisateur de la session en cours (401 sans session). */
    #[Route('/moi', name: 'api_moi', methods: ['GET'])]
    public function moi(#[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        return $this->profil($utilisateur);
    }

    /**
     * Modification du profil. Changer d'e-mail (l'identifiant de connexion) ou de mot de passe
     * exige le mot de passe actuel. Le rôle ne se change que dans le backoffice, par l'administrateur.
     */
    #[Route('/moi', name: 'api_moi_modifier', methods: ['PATCH'])]
    public function modifier(#[CurrentUser] Utilisateur $utilisateur, Request $request): JsonResponse
    {
        $donnees = $request->toArray();
        $ancienEmail = $utilisateur->getEmail();
        $this->appliquerCoordonnees($utilisateur, $donnees);

        $nouveauMotDePasse = \is_string($donnees['nouveauMotDePasse'] ?? null) ? $donnees['nouveauMotDePasse'] : '';
        $erreurs = $this->erreurs($this->validator->validate($utilisateur));
        if ('' !== $nouveauMotDePasse) {
            $erreurs += $this->erreurs($this->validator->validate($nouveauMotDePasse, self::contraintesMotDePasse()), 'nouveauMotDePasse');
        }

        $changeIdentifiants = $utilisateur->getEmail() !== $ancienEmail || '' !== $nouveauMotDePasse;
        $motDePasseActuel = \is_string($donnees['motDePasseActuel'] ?? null) ? $donnees['motDePasseActuel'] : '';
        if ($changeIdentifiants && !$this->hasher->isPasswordValid($utilisateur, $motDePasseActuel)) {
            $erreurs['motDePasseActuel'] = '' === $motDePasseActuel
                ? 'Saisissez votre mot de passe actuel pour changer d\'e-mail ou de mot de passe.'
                : 'Mot de passe actuel incorrect.';
        }

        if ([] !== $erreurs) {
            // Les modifications restent en mémoire : on les annule pour ne rien enregistrer.
            $this->em->refresh($utilisateur);

            return $this->json(['erreurs' => $erreurs], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ('' !== $nouveauMotDePasse) {
            $utilisateur->setMotDePasse($this->hasher->hashPassword($utilisateur, $nouveauMotDePasse));
        }
        $this->em->flush();

        // Nouvel e-mail ou nouveau mot de passe : la session est rafraîchie pour ne pas être déconnecté.
        if ($changeIdentifiants) {
            $this->security->login($utilisateur, 'json_login', 'main');
        }

        return $this->profil($utilisateur);
    }

    /** Interceptée par le pare-feu (logout), jamais exécutée. */
    #[Route('/deconnexion', name: 'api_deconnexion', methods: ['GET', 'POST'])]
    public function deconnexion(): never
    {
        throw new \LogicException('La déconnexion est gérée par le pare-feu.');
    }

    /** @param array<mixed> $donnees */
    private function appliquerCoordonnees(Utilisateur $utilisateur, array $donnees): void
    {
        $texte = static fn (string $cle): ?string => \is_string($donnees[$cle] ?? null) ? trim($donnees[$cle]) : null;

        if (\array_key_exists('email', $donnees)) {
            $utilisateur->setEmail(mb_strtolower($texte('email') ?? ''));
        }
        if (\array_key_exists('nom', $donnees)) {
            $utilisateur->setNom($texte('nom') ?? '');
        }
        if (\array_key_exists('prenom', $donnees)) {
            $utilisateur->setPrenom($texte('prenom') ?: null);
        }
        if (\array_key_exists('telephone', $donnees)) {
            $utilisateur->setTelephone($texte('telephone') ?: null);
        }
    }

    /** @return list<\Symfony\Component\Validator\Constraint> */
    private static function contraintesMotDePasse(): array
    {
        return [
            new Assert\NotBlank(message: 'Choisissez un mot de passe.'),
            new Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
        ];
    }

    /** @return array<string, string> premier message par champ */
    private function erreurs(ConstraintViolationListInterface $violations, ?string $champ = null): array
    {
        $erreurs = [];
        foreach ($violations as $violation) {
            $erreurs[$champ ?? $violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $erreurs;
    }

    private function profil(Utilisateur $utilisateur, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'email' => $utilisateur->getEmail(),
            'prenom' => $utilisateur->getPrenom(),
            'nom' => $utilisateur->getNom(),
            'telephone' => $utilisateur->getTelephone(),
            'role' => $utilisateur->getRole()->value,
        ], $status);
    }
}
