<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Langue;
use App\Enum\TypeDeVoyage;
use App\Repository\DemandeRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Remplit la fiche de la demande courante au fil de la conversation (docs/outils.md).
 * Version simple du ticket #192 ; la validation complète et le lien au client viennent avec le ticket #194.
 */
final class EnregistrerExigences implements Outil
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly DemandeRepository $demandes,
    ) {
    }

    public function nom(): string
    {
        return 'enregistrer_exigences';
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Enregistre dans la fiche demande toutes les exigences que contient le dernier message du client : destination ou zone dans lieuOuZone, dates, durée, voyageurs, ville de départ, budget, envies, contraintes. N\'envoie que les champs nouveaux ou modifiés et omets les autres. Une liste (envies, contraintes, âges des enfants) remplace la liste déjà enregistrée : renvoie-la en entier. Passe recapitulatifConfirme à true seulement quand le client a confirmé le récapitulatif. Les textes de la fiche (lieu, ville de départ, envies, contraintes) s\'écrivent toujours en français, même si le client écrit en anglais : « Lisbonne », « plage ».',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'typeDeVoyage' => ['type' => 'string', 'enum' => array_map(static fn (TypeDeVoyage $t) => $t->value, TypeDeVoyage::cases())],
                    'lieuOuZone' => ['type' => 'string', 'description' => 'Lieu précis ou zone, tel que le client l\'exprime : « Lisbonne », « la Méditerranée ».'],
                    'villeDepart' => ['type' => 'string'],
                    'dateDebut' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'],
                    'dateFin' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'],
                    'datesFlexibles' => ['type' => 'boolean'],
                    'nombreDeJours' => ['type' => 'integer', 'description' => 'Nombre de nuits sur place.'],
                    'adultes' => ['type' => 'integer'],
                    'enfants' => ['type' => 'integer'],
                    'agesEnfants' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'budgetEuros' => ['type' => 'integer', 'description' => 'Le montant tel que le client le donne, sans calcul : « 900 € par personne » donne 900 avec budgetParPersonne à true.'],
                    'budgetParPersonne' => ['type' => 'boolean', 'description' => 'true : le budget est par personne ; false : c\'est un total.'],
                    'transportInclus' => ['type' => 'boolean'],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Activités, ambiance, rythme (« plage », « gastronomie »), y compris l\'ambiance qui découle du projet : « romantique » pour un voyage de noces, « famille » avec des enfants, « nature » pour des paysages ou des aurores boréales, « détente » si le client veut se reposer. Une préférence de climat y va aussi, avec les mots du client : « de la chaleur », « au moins 25 °C », « pas trop chaud ». Pas la destination : elle va dans lieuOuZone.'],
                    'contraintes' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mobilité réduite (avec le type de besoin), régime, allergie (avec sa gravité), santé, animaux.'],
                    'client' => [
                        'type' => 'object',
                        'properties' => ['prenom' => ['type' => 'string'], 'email' => ['type' => 'string'], 'telephone' => ['type' => 'string']],
                        'required' => [],
                        'additionalProperties' => false,
                    ],
                    'recapitulatifConfirme' => ['type' => 'boolean'],
                    'langue' => ['type' => 'string', 'enum' => array_column(Langue::cases(), 'value'), 'description' => 'Langue dans laquelle le client écrit : « en » s\'il écrit en anglais, « fr » sinon. À envoyer dès son premier message, puis s\'il change de langue.'],
                ],
                'required' => ['recapitulatifConfirme'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $demande = $contexte->demande;

        try {
            $this->appliquer($demande, $entree);

            $violations = $this->validator->validate($demande);
            if (\count($violations) > 0) {
                $violation = $violations->get(0);
                throw new OutilException(\sprintf('Champ « %s » refusé : %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            // En dernier : un compte client n'est créé que si le reste de la fiche est valide.
            if (\is_array($entree['client'] ?? null)) {
                $this->lierClient($demande, $entree['client']);
            }
        } catch (OutilException $e) {
            // Rien n'est enregistré : la fiche revient à son dernier état valide.
            $this->em->refresh($demande);

            throw $e;
        }

        $demande->setModifieLe(new \DateTimeImmutable());
        $confirme = true === ($entree['recapitulatifConfirme'] ?? false);
        $indispensables = self::indispensablesManquants($demande);
        if ($confirme && [] !== $indispensables) {
            // Les exigences de ce message restent enregistrées ; seule la confirmation est refusée.
            $this->em->flush();

            throw new OutilException(\sprintf('Récapitulatif non confirmé : la fiche ne contient pas encore %s. Enregistre d\'abord toutes les exigences déjà données par le client, y compris dans ses messages précédents, puis rappelle cet outil avec recapitulatifConfirme à true.', implode(', ', $indispensables)));
        }
        if ($confirme) {
            // Le client a validé sa demande : la conversation de recueil est aboutie.
            $contexte->conversation->setFinLe(new \DateTimeImmutable());
        }
        $this->em->flush();

        return [
            'fiche' => self::fiche($demande),
            'exigencesManquantes' => self::manquantes($demande),
            'recapitulatifConfirme' => $confirme,
            'langue' => $demande->getLangue()->value,
        ];
    }

    /**
     * @param array<mixed> $entree
     */
    private function appliquer(Demande $demande, array $entree): void
    {
        if (\array_key_exists('langue', $entree)) {
            $langue = Langue::tryFrom((string) $entree['langue'])
                ?? throw new OutilException(\sprintf('Langue « %s » inconnue : « fr » ou « en ».', (string) $entree['langue']));
            $demande->setLangue($langue);
        }
        if (\array_key_exists('typeDeVoyage', $entree)) {
            $type = TypeDeVoyage::tryFrom((string) $entree['typeDeVoyage'])
                ?? throw new OutilException(\sprintf('Type de voyage « %s » inconnu.', (string) $entree['typeDeVoyage']));
            $demande->setTypeDeVoyage($type);
        }
        if (\array_key_exists('lieuOuZone', $entree)) {
            $demande->setLieuOuZone(self::texte($entree['lieuOuZone'], 255));
        }
        if (\array_key_exists('villeDepart', $entree)) {
            $demande->setVilleDepart(self::texte($entree['villeDepart'], 100));
        }
        if (\array_key_exists('dateDebut', $entree)) {
            $demande->setDateDebut(self::date($entree['dateDebut'], 'dateDebut'));
        }
        if (\array_key_exists('dateFin', $entree)) {
            $demande->setDateFin(self::date($entree['dateFin'], 'dateFin'));
        }
        if (\array_key_exists('datesFlexibles', $entree)) {
            $demande->setDatesFlexibles((bool) $entree['datesFlexibles']);
        }
        if (\array_key_exists('nombreDeJours', $entree)) {
            $demande->setNombreDeJours((int) $entree['nombreDeJours']);
        }
        if (\array_key_exists('adultes', $entree)) {
            $demande->setAdultes((int) $entree['adultes']);
        }
        if (\array_key_exists('enfants', $entree)) {
            $demande->setEnfants((int) $entree['enfants']);
        }
        if (\is_array($entree['agesEnfants'] ?? null)) {
            $demande->setAgesEnfants(array_values(array_map('intval', $entree['agesEnfants'])));
        }
        if (\array_key_exists('budgetEuros', $entree)) {
            $demande->setBudgetCentimes((int) $entree['budgetEuros'] * 100);
        }
        if (\array_key_exists('budgetParPersonne', $entree)) {
            $demande->setBudgetParPersonne((bool) $entree['budgetParPersonne']);
        }
        if (\array_key_exists('transportInclus', $entree)) {
            $demande->setTransportInclus((bool) $entree['transportInclus']);
        }
        if (\is_array($entree['envies'] ?? null)) {
            $demande->setEnvies(self::liste($entree['envies']));
        }
        if (\is_array($entree['contraintes'] ?? null)) {
            $demande->setContraintes(self::liste($entree['contraintes']));
        }
    }

    /**
     * Rattache la demande au compte client de cet e-mail, créé au besoin. Un compte existant n'est jamais
     * modifié depuis le chatbot : le visiteur n'a pas prouvé qu'il en est le titulaire.
     *
     * @param array<mixed> $client
     */
    private function lierClient(Demande $demande, array $client): void
    {
        $email = mb_strtolower(trim((string) ($client['email'] ?? '')));
        if ('' === $email) {
            return;
        }

        $utilisateur = $this->utilisateurs->findOneBy(['email' => $email]);
        if (null === $utilisateur) {
            $prenom = self::texte($client['prenom'] ?? null, 100);
            $utilisateur = (new Utilisateur())
                ->setEmail($email)
                ->setPrenom($prenom)
                // Le nom est obligatoire sur un compte : le conseiller le complète.
                ->setNom($prenom ?? 'À compléter')
                ->setTelephone(self::texte($client['telephone'] ?? null, 30));

            $violations = $this->validator->validate($utilisateur);
            if (\count($violations) > 0) {
                throw new OutilException(\sprintf('Coordonnées refusées : %s', $violations->get(0)->getMessage()));
            }
            $this->em->persist($utilisateur);
        } elseif ($demande->getClient() !== $utilisateur
            && $this->demandes->compterEnCoursDuClient($utilisateur, $demande) >= Demande::VOYAGES_EN_COURS_MAX) {
            throw new OutilException(\sprintf('Coordonnées non enregistrées : ce client a déjà %d voyages en cours, le maximum. Dis-lui qu\'il peut poursuivre l\'un d\'eux depuis « Mon espace » sur le site, ou demander à son conseiller d\'en clore un pour en commencer un nouveau. N\'enregistre pas ses coordonnées sur cette demande.', Demande::VOYAGES_EN_COURS_MAX));
        }

        $demande->setClient($utilisateur);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fiche(Demande $demande): array
    {
        $budget = $demande->getBudgetCentimes();

        return [
            'typeDeVoyage' => $demande->getTypeDeVoyage()?->value,
            'lieuOuZone' => $demande->getLieuOuZone(),
            'villeDepart' => $demande->getVilleDepart(),
            'dateDebut' => $demande->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $demande->getDateFin()?->format('Y-m-d'),
            'datesFlexibles' => $demande->isDatesFlexibles(),
            'nombreDeJours' => $demande->getNombreDeJours(),
            'adultes' => $demande->getAdultes(),
            'enfants' => $demande->getEnfants(),
            'agesEnfants' => $demande->getAgesEnfants(),
            'budgetEuros' => null === $budget ? null : intdiv($budget, 100),
            'budgetParPersonne' => $demande->isBudgetParPersonne(),
            'transportInclus' => $demande->isTransportInclus(),
            'envies' => $demande->getEnvies(),
            'contraintes' => $demande->getContraintes(),
        ];
    }

    /**
     * Exigences encore inconnues, parmi celles que la fiche sait distinguer d'une valeur par défaut.
     *
     * @return list<string>
     */
    public static function manquantes(Demande $demande): array
    {
        $manquantes = [
            'type de voyage' => null === $demande->getTypeDeVoyage(),
            'lieu ou zone' => null === $demande->getLieuOuZone(),
            'dates' => null === $demande->getDateDebut(),
            'nombre de jours' => null === $demande->getNombreDeJours(),
            'ville de départ' => null === $demande->getVilleDepart(),
            'budget' => null === $demande->getBudgetCentimes(),
            'envies' => [] === $demande->getEnvies(),
        ];

        return array_keys(array_filter($manquantes));
    }

    /**
     * Sans elles, la demande est inutilisable par le conseiller : le récapitulatif ne peut pas être confirmé.
     * Le budget n'en fait pas partie (sans budget, trois fourchettes sont proposées).
     *
     * @return list<string>
     */
    private static function indispensablesManquants(Demande $demande): array
    {
        $manquants = [
            'le type de voyage' => null === $demande->getTypeDeVoyage(),
            'le lieu ou la zone' => null === $demande->getLieuOuZone(),
            'les dates' => null === $demande->getDateDebut() && null === $demande->getNombreDeJours(),
        ];

        return array_keys(array_filter($manquants));
    }

    private static function texte(mixed $valeur, int $longueurMax): ?string
    {
        $texte = trim((string) $valeur);

        return '' === $texte ? null : mb_substr($texte, 0, $longueurMax);
    }

    private static function date(mixed $valeur, string $champ): ?\DateTimeImmutable
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valeur);
        if (false === $date || $date->format('Y-m-d') !== $valeur) {
            throw new OutilException(\sprintf('%s doit être une date au format AAAA-MM-JJ, reçu « %s ».', $champ, (string) $valeur));
        }

        return $date;
    }

    /**
     * @param array<mixed> $valeurs
     *
     * @return list<string>
     */
    private static function liste(array $valeurs): array
    {
        return array_values(array_filter(array_map(static fn ($v) => self::texte($v, 255), $valeurs)));
    }
}
