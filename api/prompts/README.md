# Prompts des agents

| Fichier | Agent | Ticket |
| --- | --- | --- |
| `conversation.md` | Conversation : accueille le client et recueille ses exigences | #177 |
| `recherche.md` | Recherche : choisit la destination, les logements et les activités, explique son choix au client, conseille un niveau et propose les options (le site affiche la piste en carte) | #203 |
| `redaction.md` | Chiffrage et rédaction : rédige la première proposition (programme jour par jour, points forts, argumentaire) et l'enregistre ; évaluée par `make evaluer-proposition` (voir `docs/outils.md`) | #207 |

Les commentaires `<!-- … -->` en tête de fichier ne sont pas envoyés au modèle. Les variables `{{…}}` sont remplacées par l'API avant chaque appel.

## Tester le prompt de conversation

Dans la [console Claude](https://platform.claude.com), ouvrir le Workbench (les essais consomment les crédits API de l'agence) :

1. Coller le contenu de `conversation.md` dans le prompt système, sans le commentaire de tête, en remplaçant `{{date_du_jour}}` par la date du jour.
2. Choisir le modèle à tester (`claude-sonnet-5-5` ou `claude-haiku-5-5`), effort `low`.
3. Jouer le client du profil, en ne donnant les informations que lorsque le chatbot les demande (ou comme un vrai client les donnerait spontanément).
4. Remplir la grille ci-dessous, puis ajuster le prompt et recommencer si un critère échoue.

Critères d'acceptation : pour chaque profil, toutes les exigences sont recueillies **en moins de 12 échanges**, et le chatbot n'annonce **jamais de prix ferme ni de disponibilité**.

## Les 5 profils

Chaque profil reprend une demande test de `api/tests/demandes`, où se trouvent la fiche attendue et les points de contrôle.

| Profil | Demande test | Le client | Ce qu'il doit révéler | À vérifier en plus |
| --- | --- | --- | --- | --- |
| Couple | `02-sejour-lisbonne-mobilite-reduite` | « Mon mari et moi voudrions un long week-end à Lisbonne pour bien manger. » | Du 14 au 17 mai 2027, départ de Lyon, 1 300 € à deux vols compris, fauteuil roulant | Demande le type de besoin d'accessibilité |
| Famille | `01-sejour-famille-tenerife` | « Un endroit au soleil pour les vacances de février avec nos deux enfants. » | 2 adultes, enfants de 6 et 9 ans, au moins 18 °C et une plage, du 13 au 20 février 2027, départ de Bordeaux, 3 500 € au total, voir des dauphins | Demande l'âge des enfants |
| Groupe | `05-groupe-rome-association` | « Une association culturelle : 35 adhérents à Rome au printemps. » | Du 15 au 19 avril 2027, départ de Lyon, adultes, 450 € par personne transport compris, Colisée avec guide | Demande la nature du groupe et le budget par personne |
| Noces | `07-noces-santorin` | « Nous nous marions le 5 juin et rêvons de Santorin. » | 7 nuits à partir du 12 juin 2027, départ de Paris, 5 000 € au total, piscine privée, bateau, allergie aux fruits de mer | Demande la gravité de l'allergie et dit qu'elle sera signalée au conseiller |
| Entreprise | `09-entreprise-seminaire-marrakech` | « Le séminaire annuel de notre entreprise à Marrakech. » | 40 collaborateurs, du 10 au 12 mars 2027, départ de Paris, 900 € par personne transport compris, salle plénière, activité de cohésion | Demande l'objectif du séminaire |

Pour chaque profil, tenter aussi au moins une fois : « Combien ça va coûter exactement ? » et « Il reste de la place ? ». Le chatbot doit expliquer qu'il ne donne ni prix ferme ni disponibilité.

## Évaluation automatique (#195)

`make evaluer` rejoue les 10 demandes test de `api/tests/demandes` avec l'agent Conversation réel (prompt, outils, base), puis compare la fiche demande obtenue à `exigencesAttendues`, exigence par exigence. `make evaluer d="01 04"` ne rejoue que ces demandes ; `make evaluer d="-r 3"` rejoue chaque demande trois fois, car le chatbot ne répond pas deux fois pareil.

- **Client** : joué par Claude Sonnet 5.5, qui ne connaît que les messages du fichier de test et n'invente rien (`src/Chatbot/Evaluation/ClientSimule.php`).
- **Comparaison** : les champs chiffrés, les dates et les booléens sont comparés à l'identique (`ComparateurFiche`). Le lieu, les envies et les contraintes sont jugés sur le sens par Claude Opus 5.5 (`Juge`), qui relit aussi la conversation : questions de la table d'adaptation jamais posées, exigences absentes du récapitulatif (critères de #193), prix ferme ou disponibilité annoncés.
- **Taux d'exigences captées** = champs justes / champs que le client a exprimés (une valeur attendue nulle n'est pas une exigence, mais une valeur inventée à sa place est signalée « en trop »).
- **Base** : chaque conversation tourne dans une transaction annulée à la fin ; `--garder` la conserve pour la relire dans le backoffice.
- **Rapport** : `api/evaluations/evaluation-<date>.md` (hors dépôt), avec le tableau à coller dans le ticket, les écarts et chaque conversation.

Appelle la vraie API Claude : environ 0,07 $ par demande test, soit 0,70 $ les dix (mesuré sur la demande 01 : 0,02 $ de chatbot, 0,02 $ de client simulé, 0,03 $ de juge).

## Grille de résultats

Tests automatisés du 16/10/2026 : le client est joué par Claude Sonnet 5.5 à partir du profil, la conversation est notée par Claude Opus 5.5 (juge indépendant des deux modèles testés). Une conversation par profil et par modèle. « Exigences connues au message » = numéro du message client après lequel les dix exigences sont connues.

| Version du prompt | Modèle | Profil | Messages du client | Exigences connues au message | Exigences manquantes | Prix ou disponibilité annoncés ? | Ton (sur 5) | Remarques |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| v1 | Haiku 5.5 | Couple | 6 | — | Flexibilité des dates, températures | Non | 5 | Type de besoin d'accessibilité non demandé |
| v1 | Haiku 5.5 | Groupe | 6 | — | Flexibilité des dates, températures | Non | 5 | |
| v1 | Haiku 5.5 et Sonnet 5.5 | Les 8 autres | 6 à 8 | 3 à 5 | Températures (7 sur 8) | Non | 4 à 5 | Haiku : objectif du séminaire non demandé ; Sonnet : erreur sur la date de Pâques ; récapitulatifs longs |
| v2 | Haiku 5.5 | Couple | 9 | 5 | Aucune | Non | 5 | |
| v2 | Haiku 5.5 | Famille | 7 | 5 | Aucune | Non | 5 | Une faute d'accord, une question d'ambiance redondante |
| v2 | Haiku 5.5 | Groupe | 6 | 5 | Aucune | Non | 5 | Pas de question sur la répartition des chambres |
| v2 | Haiku 5.5 | Noces | 8 | 5 | Aucune | Non | 5 | Gravité de l'allergie demandée et signalée |
| v2 | Haiku 5.5 | Entreprise | 7 | 6 | Aucune | Non | 5 | Objectif du séminaire demandé |
| v2 | Sonnet 5.5 | Couple | 7 | 4 | Aucune | Non | 5 | |
| v2 | Sonnet 5.5 | Famille | 6 | 5 | Aucune | Non | 5 | |
| v2 | Sonnet 5.5 | Groupe | 7 | 6 | Aucune | Non | 5 | |
| v2 | Sonnet 5.5 | Noces | 8 | 6 | Aucune | Non | 5 | |
| v2 | Sonnet 5.5 | Entreprise | 8 | 6 | Aucune | Non | 5 | |

Corrections de la v2 : les températures, la flexibilité des dates et le nombre d'adultes sont toujours demandés ; l'objectif d'un événement d'entreprise et le type de besoin d'accessibilité aussi ; récapitulatif limité à huit lignes ; aucune affirmation sur le calendrier ou les jours fériés.

Coût d'une conversation de recueil (mesuré) : environ 0,004 $ avec Haiku 5.5 et 0,13 $ avec Sonnet 5.5.

## Essais de l'agent Recherche

Essais réels du 20/10/2026 avec Claude Sonnet 5.5 (`recherche.md`), sur les 10 demandes test : fiche demande et messages du client tirés de `api/tests/demandes`, catalogue de démo, chaque essai dans une transaction annulée. « Niveaux identiques » = niveaux dont les logements et activités sont ceux de `attendu.budgetsParNiveau` (27 niveaux chiffrables, l'Islande passant au conseiller).

| Version du prompt | Bonne destination | Hors contrainte | Islande transmise au conseiller | Niveaux identiques | Gras markdown | Mention « prix estimés » | Remarques |
| --- | --- | --- | --- | --- | --- | --- | --- |
| v1 | 10 / 10 | 0 | Oui | 12 / 27 | 9 messages | 9 / 9 | Activités demandées dans la conversation (dauphins, kayak, Colisée) seulement suggérées : l'agent ne voyait que la fiche ; logement simple absent de l'essentiel (Santorin) ; texte écrit avant un appel d'outil affiché au client |
| v2 | 10 / 10 | 0 | Oui | 16 / 27 | 0 | 9 / 9 | Messages du client fournis ; « privatiser un riad » ignoré pour l'essentiel (kasbah) ; bungalow de surf en essentiel d'un voyage de noces |
| v3 | 10 / 10 | 0 | Oui | 18 / 27 | 1 message | 9 / 9 | Riad aux trois niveaux ; à Santorin, « piscine privée » traitée comme un type de logement imposé (pas d'hôtel simple) ; autres écarts : choix d'activités suggérées (cours de pâtes, hammam, surf) |

Corrections hors prompt après ces essais : l'agent Recherche n'envoie au client que son message final, sans le gras markdown, et les envies sont aussi rapprochées des équipements, catégories et noms des prestations.

Aucune combinaison hors contrainte : `retenir_combinaison` revérifie chaque choix et aucun n'a été refusé. Coût mesuré d'une recherche : environ 27 000 tokens d'entrée et 1 500 de sortie, 7 à 21 secondes.

### Sans combinaison possible (ticket #204, prompt v4)

Essais réels du 20/10/2026, mêmes conditions. Aucun prix ni prestation inventés ; les ajustements cités sont ceux que calcule `chercher_destinations`.

| Scénario | Outils appelés | Résultat |
| --- | --- | --- |
| Période non couverte : Islande du 16 au 22 janvier 2027 (demande test 04) | `chercher_destinations`, `transmettre_au_conseiller` | Transmis au conseiller ; climat saisi cité (0 °C, 90 mm) ; ajustements proposés : du 1er au 7 mai 2027, ou Costa Rica, Marrakech, Tenerife aux mêmes dates |
| Capacité : chorale de 60 personnes à Santorin | `chercher_destinations` | Pas de transmission (un ajustement existe) : Rome, seule destination qui accueille 60 personnes, en précisant qu'elle ne répond pas aux envies |
| Température : Lisbonne à 20 °C minimum début février, dates flexibles | `chercher_destinations` | Pas de transmission : Lisbonne du 1er au 5 juin 2027, ou Bali et Costa Rica aux mêmes dates |

## Ton de voix Détour (brief de rebranding)

Le brief d'octobre 2026 fixe la personnalité de la marque : « malicieuse, précise, chaleureuse ; jamais pressée, jamais criarde », précise d'abord, malicieuse ensuite. Une pointe d'humour par message au plus, jamais sur un prix ni sur un problème ; « nous » pour l'agence, « je » pour l'assistant ; prix toujours daté (mention `avertissement` de `retenir_combinaison`).

Essais réels du 22/10/2026 (`make evaluer`, chatbot Haiku 5.5 ; `make evaluer-proposition`, recherche et rédaction Sonnet 5.5) :

| Prompts | Demandes | Précis, prix datés | Jamais pressé ni criard | Malicieux | Défauts relevés, corrigés dans la version suivante |
| --- | --- | --- | --- | --- | --- |
| conversation v7, recherche v7, rédaction v5 | 08 à 10 (conversation), 01 à 10 (propositions) | Oui | Oui (aucun superlatif, un seul « ! » sur 10 propositions) | Recherche et rédaction oui ; conversation jamais | Conversation administrative (« Noté. »), gras markdown ; humour sur une envie sans réponse ; trois ou quatre traits d'humour par programme |
| conversation v8, recherche v8, rédaction v6 | 06, 08 (conversation), 01, 06 (propositions) | Oui | Oui | Oui, un trait par message ou par programme | Message écrit avant `enregistrer_exigences` puis répété ; prix d'achat `aPartirDeParNuitEuros` cité par l'assistant ; exemples du prompt recopiés mot pour mot |
| conversation v8 corrigée | 06, 08 (conversation) | Oui | Oui | Oui | Plus de message en double ni de prix d'achat cité. Les exemples d'humour du prompt ne reprennent plus les demandes test |
