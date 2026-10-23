<?php

namespace App\DataFixtures;

use App\DataFixtures\Donnees\Catalogue;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Entity\Utilisateur;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogue de démo : destinations et climat, prestataires, logements, activités et leurs tarifs.
 * Les données sont dans Donnees/Catalogue.php.
 */
final class CatalogueFixtures extends Fixture implements DependentFixtureInterface
{
    /** Conseiller qui « saisit » le catalogue des destinations d'origine ; les autres indiquent le leur (saisiPar). */
    private const SAISI_PAR = [
        'lisbonne' => UtilisateurFixtures::LEA, 'marrakech' => UtilisateurFixtures::LEA,
        'santorin' => UtilisateurFixtures::LEA, 'rome' => UtilisateurFixtures::LEA,
        'tenerife' => UtilisateurFixtures::HUGO, 'islande' => UtilisateurFixtures::HUGO,
        'bali' => UtilisateurFixtures::HUGO, 'costa-rica' => UtilisateurFixtures::HUGO,
    ];

    /** @var array<string, Destination> */
    private array $destinations = [];
    /** @var array<string, list<array{int, int, float}>> */
    private array $saisons = [];
    /** @var array<string, string> référence du conseiller, par destination */
    private array $saisiPar = [];
    /** @var array<string, Prestataire> */
    private array $prestataires = [];

    public function __construct(private readonly Photos $photos)
    {
    }

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->chargerDestinations($manager);
        $this->chargerPrestataires($manager);

        foreach (Catalogue::logements() as $donnees) {
            $this->chargerPrestation($manager, TypePrestation::Logement, $donnees);
        }
        foreach (Catalogue::activites() as $donnees) {
            $this->chargerPrestation($manager, TypePrestation::Activite, $donnees);
        }

        $manager->flush();
    }

    private function chargerDestinations(ObjectManager $manager): void
    {
        foreach (Catalogue::destinations() as $slug => $d) {
            $this->saisiPar[$slug] = self::SAISI_PAR[$slug] ?? $d['saisiPar'];
            $destination = (new Destination())
                ->setSlug($slug)
                ->setPays($d['pays'])
                ->setRegion($d['region'])
                ->setVille($d['ville'])
                ->setDescription($d['description'])
                ->setPointsForts($d['pointsForts'])
                ->setTempsDeTrajet($d['tempsDeTrajet'])
                ->setPhoto($this->photos->copier('destinations', $d['photo']))
                ->setPhotoAlt($d['photoAlt'])
                ->setNom($d['nom'])
                ->setAccroche($d['accroche'])
                ->setTypesDeVoyage($d['typesDeVoyage'])
                ->setZones($d['zones'])
                ->setMoisIdeal($d['moisIdeal'])
                ->setLatitude($d['latitude'])
                ->setLongitude($d['longitude'])
                ->setPubliee(true)
                ->setMiseEnAvant($d['miseEnAvant']);

            foreach ($d['temperatures'] as $index => $temperature) {
                [$debut, $fin] = $this->mois($index + 1, $index + 1);
                $destination->addPeriodeClimatique(
                    (new PeriodeClimatique())
                        ->setDebut($debut)
                        ->setFin($fin)
                        ->setTemperatureMoyenne($temperature)
                        ->setPluieMm($d['pluie'][$index])
                        ->setSaisiPar($this->conseiller($slug)),
                );
            }

            $manager->persist($destination);
            foreach ($destination->getPeriodesClimatiques() as $periode) {
                $manager->persist($periode);
            }
            $this->destinations[$slug] = $destination;
            $this->saisons[$slug] = $d['saisons'];
        }
    }

    private function chargerPrestataires(ObjectManager $manager): void
    {
        foreach (Catalogue::prestataires() as $cle => $p) {
            $fourchettes = [];
            foreach ($p['vols'] ?? [] as $arrivee => $departs) {
                foreach ($departs as $depart => [$min, $max]) {
                    $fourchettes[] = ['depart' => $depart, 'arrivee' => $arrivee, 'minCentimes' => $min * 100, 'maxCentimes' => $max * 100];
                }
            }
            foreach ($p['transferts'] ?? [] as $ville => [$min, $max]) {
                $fourchettes[] = ['depart' => 'Aéroport de '.$ville, 'arrivee' => $ville, 'minCentimes' => $min * 100, 'maxCentimes' => $max * 100];
            }

            $prestataire = (new Prestataire())
                ->setNom($p['nom'])
                ->setType(TypePrestataire::from($p['type']))
                ->setContactNom($p['contactNom'])
                ->setEmail($p['email'])
                ->setTelephone($p['telephone'])
                ->setConditionsAnnulation($p['conditionsAnnulation'])
                ->setFourchettesTransport($fourchettes);

            $manager->persist($prestataire);
            $this->prestataires[$cle] = $prestataire;
        }
    }

    /**
     * @param array<string, mixed> $p
     */
    private function chargerPrestation(ObjectManager $manager, TypePrestation $type, array $p): void
    {
        $slug = $p['destination'];
        $prestation = (new Prestation())
            ->setType($type)
            ->setNom($p['nom'])
            ->setDestination($this->destinations[$slug])
            ->setPrestataire($this->prestataires[$p['prestataire']])
            ->setCategorie($p['categorie'])
            ->setDescription($p['description'] ?? null)
            ->setCapacite($p['capacite'])
            ->setChambres($p['chambres'] ?? null)
            ->setEquipements($p['equipements'] ?? [])
            ->setAccessible($p['accessible'] ?? false)
            ->setDureeMinutes($p['duree'] ?? null)
            ->setAgeMinimum($p['ageMinimum'] ?? null)
            ->setEnvies($p['envies'])
            ->setPhotos(array_map(fn (string $photo): string => $this->photos->copier('prestations', $photo), $p['photos']));

        foreach ($p['saisons'] ?? $this->saisons[$slug] as [$moisDebut, $moisFin, $coefficient]) {
            [$debut, $fin] = $this->mois($moisDebut, $moisFin);
            $prestation->addTarif(
                (new TarifParPeriode())
                    ->setDebut($debut)
                    ->setFin($fin)
                    ->setPrixCentimes((int) round($p['prix'] * $coefficient) * 100)
                    ->setUnite(TypePrestation::Logement === $type ? UniteTarif::ParNuit : UniteTarif::ParPersonne)
                    ->setSejourMinimumNuits($p['sejourMinimum'] ?? null)
                    ->setSaisiPar($this->conseiller($slug)),
            );
        }

        $manager->persist($prestation);
        foreach ($prestation->getTarifs() as $tarif) {
            $manager->persist($tarif);
        }
    }

    /**
     * Du premier jour du mois de début au dernier jour du mois de fin.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function mois(int $debut, int $fin): array
    {
        $premier = new \DateTimeImmutable(\sprintf('%d-%02d-01', Catalogue::ANNEE, $debut));
        $dernier = (new \DateTimeImmutable(\sprintf('%d-%02d-01', Catalogue::ANNEE, $fin)))->modify('last day of this month');

        return [$premier, $dernier];
    }

    private function conseiller(string $slug): Utilisateur
    {
        return $this->getReference($this->saisiPar[$slug], Utilisateur::class);
    }
}
