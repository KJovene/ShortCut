<?php

namespace App\DataFixtures\Donnees;

/**
 * Contenus de démo des pages du site (HTML simple, modifiable dans le backoffice).
 * Dans un titre, les mots entre astérisques sont mis en valeur sur le site (italique de couleur).
 * Les pages légales sont des modèles marqués « à valider » tant que l'agence n'a pas fourni ses textes.
 */
final class Pages
{
    private const A_VALIDER = '<p><strong>Modèle à valider par l\'agence</strong> : ce texte doit être relu et complété avant la mise en ligne.</p>';

    /**
     * @return list<array{slug: string, titre: string, seoTitre: string, seoDescription: string, contenu: string, photos: list<string>, ordreMenu: ?int}>
     */
    public static function toutes(): array
    {
        return [
            [
                'slug' => 'accueil',
                'titre' => 'Où irez-vous *chercher le soleil* ?',
                'seoTitre' => 'Agence de voyage sur mesure',
                'seoDescription' => 'Décrivez vos envies à notre assistant : il vous propose un voyage complet et chiffré, qu\'un conseiller confirme ensuite.',
                'contenu' => '<p>Dites-nous où vous rêvez d\'aller, quand, avec qui et pour quel budget. Notre assistant vous prépare une première proposition complète : destination, programme jour par jour, hébergements, activités et budget en trois niveaux.</p><p>Un conseiller de l\'agence vérifie ensuite les prix et les disponibilités, puis vous confirme votre voyage.</p>',
                'photos' => ['accueil'],
                'ordreMenu' => null,
            ],
            [
                'slug' => 'types-de-voyages',
                'titre' => 'Tous les voyages, *sur mesure*',
                'seoTitre' => 'Séjours, circuits, groupes, voyages de noces et entreprise',
                'seoDescription' => 'Séjours détente, circuits accompagnés, voyages de groupe, voyages de noces et séminaires : l\'agence organise tous vos voyages.',
                'contenu' => '<h2>Séjours</h2><p>Une destination, un hébergement choisi pour vous et des activités à la carte : plage, ville ou nature.</p><h2>Circuits</h2><p>Plusieurs étapes, un itinéraire pensé jour par jour, avec guide ou en autotour : Islande, Costa Rica…</p><h2>Groupes</h2><p>Amis, associations, scolaires : hébergements adaptés, transferts et visites guidées pour 10 à 60 personnes.</p><h2>Voyages de noces</h2><p>Suites avec vue, villas avec piscine privée, attentions pour les jeunes mariés.</p><h2>Entreprise</h2><p>Séminaires, conventions et incentives : salles de réunion, hébergement en bloc et activités de cohésion.</p>',
                'photos' => ['sejour', 'circuit', 'groupe', 'noces', 'entreprise'],
                'ordreMenu' => 2,
            ],
            [
                'slug' => 'agence',
                'titre' => 'Des conseillers qui *connaissent le terrain*',
                'seoTitre' => 'Notre agence et nos conseillers',
                'seoDescription' => 'Une équipe de conseillers spécialistes de chaque destination, qui confirment chaque proposition avant votre départ.',
                'contenu' => '<p>Depuis plus de quinze ans, nos conseillers construisent des voyages sur mesure. Chacun suit ses destinations, connaît les hébergeurs et les guides sur place, et met à jour les tarifs saison après saison.</p><h2>Nos engagements</h2><ul><li>Une première proposition complète en quelques minutes, à toute heure.</li><li>Un conseiller référent qui confirme les prix et les disponibilités sous deux jours ouvrés.</li><li>Des prix transparents : le détail de chaque poste et les hypothèses de budget.</li></ul>',
                'photos' => ['agence'],
                'ordreMenu' => 3,
            ],
            [
                'slug' => 'contact',
                'titre' => 'Une question ? *Parlons-en*',
                'seoTitre' => 'Contacter l\'agence',
                'seoDescription' => 'Posez vos questions à notre assistant ou demandez à parler à un conseiller.',
                'contenu' => '<p>Le plus rapide : ouvrez l\'assistant en bas de l\'écran et décrivez votre projet. Vous pouvez à tout moment lui demander de parler à un conseiller.</p><h2>Horaires des conseillers</h2><p>Du lundi au vendredi de 9 h à 18 h 30, le samedi de 10 h à 17 h.</p><h2>Adresse</h2><p>12 rue des Voyageurs, 75000 Paris (adresse fictive de démonstration).</p>',
                'photos' => ['contact'],
                'ordreMenu' => 4,
            ],
            [
                'slug' => 'mentions-legales',
                'titre' => 'Mentions légales',
                'seoTitre' => 'Mentions légales',
                'seoDescription' => 'Éditeur du site, hébergement et immatriculation de l\'agence.',
                'contenu' => self::A_VALIDER.'<h2>Éditeur</h2><p>[Raison sociale], [forme juridique] au capital de [montant] €, immatriculée au RCS de [ville] sous le numéro [numéro]. Siège : [adresse]. Directeur de la publication : [nom].</p><h2>Immatriculation</h2><p>Opérateur de voyages immatriculé au registre Atout France sous le numéro [IM000000000]. Garantie financière : [organisme]. Assurance responsabilité civile professionnelle : [assureur].</p><h2>Hébergement</h2><p>[Hébergeur], [adresse].</p>',
                'photos' => [],
                'ordreMenu' => null,
            ],
            [
                'slug' => 'cgv',
                'titre' => 'Conditions générales de vente',
                'seoTitre' => 'Conditions générales de vente',
                'seoDescription' => 'Conditions de réservation, de paiement, de modification et d\'annulation des voyages.',
                'contenu' => self::A_VALIDER.'<h2>Propositions</h2><p>Les propositions remises par l\'assistant sont des estimations. Elles n\'engagent l\'agence qu\'après confirmation des prix et des disponibilités par un conseiller.</p><h2>Réservation et paiement</h2><p>[Acompte, solde, moyens de paiement.]</p><h2>Modification et annulation</h2><p>[Barème de frais selon la date d\'annulation, conditions des prestataires.]</p><h2>Frais de dossier</h2><p>Des frais de dossier de 100 à 200 € s\'appliquent une fois par dossier.</p>',
                'photos' => [],
                'ordreMenu' => null,
            ],
            [
                'slug' => 'confidentialite',
                'titre' => 'Politique de confidentialité et cookies',
                'seoTitre' => 'Confidentialité et cookies',
                'seoDescription' => 'Données collectées par l\'assistant et le site, finalités, durée de conservation et vos droits.',
                'contenu' => self::A_VALIDER.'<h2>Données collectées</h2><p>Vos messages à l\'assistant, vos exigences de voyage et, si vous les donnez, vos nom, e-mail et téléphone. Ils servent uniquement à préparer et confirmer votre proposition.</p><h2>Assistant IA</h2><p>L\'assistant est une intelligence artificielle. Vos messages sont traités par un prestataire d\'IA pour générer les réponses.</p><h2>Vos droits</h2><p>[Accès, rectification, suppression : contact et délais.]</p><h2>Cookies</h2><p>[Liste des cookies et gestion du consentement.]</p>',
                'photos' => [],
                'ordreMenu' => null,
            ],
        ];
    }
}
