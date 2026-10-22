# ShortCut

Site web d'une agence de voyage avec un chatbot qui prépare des propositions sur mesure, et un backoffice pour les conseillers.
Le cahier des charges est dans [`cahier-des-charges.md`](cahier-des-charges.md).

| Dossier | Contenu | Stack |
| --- | --- | --- |
| `api/` | API, backoffice et appels à Claude | Symfony 7.4 (PHP 8.3), API Platform, EasyAdmin, Doctrine, PostgreSQL 16 |
| `site/` | Site public et widget du chatbot | React 19, TypeScript, Vite, TanStack Router, TanStack Query, TanStack Form, Zod, Tailwind CSS |

## Prérequis

- Docker Desktop (ou Docker Engine + Compose v2) : l'API PHP 8.3, Nginx, PostgreSQL 16 et le site React (Node 24, conteneur `front`) tournent dans des conteneurs
- `make` (déjà présent sur macOS et Linux)

Pas besoin d'installer PHP, Composer, PostgreSQL ni Node.js sur sa machine.

## Installation

```bash
git clone https://github.com/KJovene/ShortCut.git
cd ShortCut
make install
```

`make install` construit l'image PHP, démarre les conteneurs, installe les dépendances (Composer et npm dans Docker), crée `api/.env.local` à partir de l'exemple, applique les migrations et charge les données de démo.
Il reste à renseigner sa clé `ANTHROPIC_API_KEY` dans `api/.env.local`.

Les fichiers `.env.local` contiennent les secrets de chacun : ils ne sont **jamais commités**. La clé API Claude n'existe que côté API, le site ne la voit jamais.

### Windows : API lente

Sous Windows, le partage de fichiers entre la machine et Docker est très lent : chaque requête Symfony peut prendre plus de 10 s. `compose.windows.yaml` place `vendor/` et `var/` dans des volumes Docker (le code reste partagé). À faire une fois, avant ou après `make install` :

```bash
cp compose.windows.yaml compose.override.yaml   # lu automatiquement par Docker Compose, non versionné
make build && make down && make up
make composer c=install                         # remplit le volume vendor/
make cc
```

`api/vendor/` et `api/var/` de la machine ne sont alors plus utilisés par le conteneur : ajouter un paquet passe toujours par `make composer`, les logs se lisent avec `make sh` (`var/log/`). Pour l'autocomplétion de l'éditeur, `docker compose cp php:/app/vendor api/` recopie les dépendances sur la machine. macOS et Linux n'ont pas besoin de cette surcharge.

Si le site ne démarre plus ensuite (`EACCES ... node_modules/.vite-temp` dans `make site-logs`), les conteneurs avaient été lancés sans `make`, donc avec un autre utilisateur. Une seule fois :

```bash
docker compose run --rm --no-deps -u 0 --entrypoint sh front -c 'chmod -R a+rwX /app/node_modules'
make up
```

## Lancer le projet

```bash
make up     # API, base et site en arrière-plan
make dev    # suit les logs du site (Ctrl+C n'arrête pas le conteneur)
```

`make start` enchaîne les deux. Le conteneur `front` lance Vite avec le rechargement à chaud : modifier un fichier de `site/` suffit. Ses dépendances sont dans un volume Docker (`node_modules` n'apparaît pas dans `site/`), réinstallées automatiquement quand `package-lock.json` change. Pour ajouter un paquet : `make site-sh` puis `npm install <paquet>`. `make down` arrête et supprime les conteneurs. Les données de la base sont conservées dans un volume Docker.

| Adresse | Service |
| --- | --- |
| http://localhost:5173 | Site React. En développement, les devtools TanStack Query et Router s'affichent en bas de l'écran. |
| http://localhost:8000/api | Documentation de l'API (API Platform) |
| `localhost:5433` | PostgreSQL (`shortcut` / `shortcut`, base `shortcut`), pour un client SQL. `make psql` ouvre une console. |

Le site appelle l'API en relatif (`/api/...`) : Vite redirige ces requêtes vers l'API, donc pas d'erreur CORS. NelmioCorsBundle autorise aussi `localhost` pour les appels directs.

Ports déjà pris sur votre machine ? Copier `.env.example` en `.env` à la racine et changer `API_PORT` ou `POSTGRES_PORT`. Le port du site se change avec `FRONT_PORT`.

## Commandes

`make` affiche la liste complète. Les plus utiles :

| Commande | Effet |
| --- | --- |
| `make migration` | Génère une migration après modification des entités |
| `make migrate` | Applique les migrations |
| `make fixtures` | Recharge le jeu de données de démo (vide la base avant) |
| `make db-reset` | Recrée la base de zéro : suppression, création, migrations, fixtures |
| `make console c="debug:router"` | Lance une commande Symfony dans le conteneur |
| `make composer c="require symfony/mailer"` | Lance Composer dans le conteneur |
| `make db-ui` | Ouvre Adminer, l'interface web de la base : http://localhost:8080 (serveur `database`, utilisateur et mot de passe `shortcut`, base `shortcut`) |
| `make schema` | Régénère le diagramme complet de la base dans [`docs/schema.md`](docs/schema.md) à partir des entités Doctrine |
| `make openapi` | Exporte la documentation OpenAPI de l'API dans [`docs/openapi.yaml`](docs/openapi.yaml) (version interactive : http://localhost:8000/api/docs) |
| `make coverage` | Couverture des tests de l'API, classe par classe (détail dans `api/var/coverage/index.html`) |
| `make sh` | Shell dans le conteneur PHP |
| `make site-sh` | Shell dans le conteneur du site (npm) |
| `make logs s=php` | Logs d'un service (`php`, `nginx`, `database`, `front`) |

## Qualité du code

```bash
make check   # tout vérifier avant une pull request
make fix     # corriger automatiquement le style (PHP-CS-Fixer et Prettier)
```

`make check` lance le style et les types (`make lint`), PHPStan (`make stan`), les tests PHPUnit et Vitest (`make test`) et le build du site.
Avant les premiers tests qui utilisent la base, préparer la base de test avec `make db-test`.

## Modèle de données

Les entités sont dans `api/src/Entity`, les enums dans `api/src/Enum`. Le diagramme complet, avec toutes les colonnes, est dans [`docs/schema.md`](docs/schema.md) (`make schema` après chaque migration). Conventions :

- **Montants en centimes** (entiers) : `budgetCentimes`, `prixCentimes`, `totalCentimes`…
- **Périodes** : `debut` et `fin` en dates (`date_immutable`), avec une contrainte qui refuse une fin avant le début.
- **Listes libres** (envies, équipements, photos, hypothèses…) en colonnes JSON.
- Tarifs et climat gardent `saisiPar` et `modifieLe` pour repérer les périodes à mettre à jour.

```mermaid
erDiagram
    CLIENT ||--o{ DEMANDE : fait
    UTILISATEUR |o--o{ DEMANDE : "suit (conseiller)"
    DEMANDE ||--|| CONVERSATION : a
    DEMANDE ||--o{ PROPOSITION : "a (versions)"
    UTILISATEUR |o--o{ PROPOSITION : confirme
    PROPOSITION ||--o{ LIGNE_DE_PRESTATION : contient
    LIGNE_DE_PRESTATION }o--o| PRESTATION : "logement ou activité"
    LIGNE_DE_PRESTATION }o--o| PRESTATAIRE : "transport ou guide"
    DESTINATION ||--o{ PERIODE_CLIMATIQUE : a
    DESTINATION ||--o{ PRESTATION : propose
    PRESTATAIRE ||--o{ PRESTATION : fournit
    PRESTATION ||--o{ TARIF_PAR_PERIODE : a
    DEMANDE |o--o{ JOURNAL_IA : trace
```

| Entité | Rôle | Champs principaux |
| --- | --- | --- |
| `Client` | Personne qui écrit au chatbot | prénom, nom, e-mail, téléphone, préférences |
| `Demande` | Fiche remplie par l'agent Conversation | type de voyage, lieu ou zone, dates et flexibilité, nombre de jours, températures min et max, adultes, enfants et âges, budget (total ou par personne, transport inclus), envies, contraintes, statut, conseiller |
| `Conversation` | Messages entre le client et le chatbot | jeton anonyme du navigateur, messages, début, fin, reprise par un conseiller |
| `Proposition` | Une version de proposition | version, type (première ou confirmée), statut, lien public, programme jour par jour, niveau de budget détaillé, totaux des trois niveaux, frais de dossier, hypothèses, confirmée par et le |
| `LigneDePrestation` | Poste chiffré d'une proposition | type (logement, transport, activité, guide), libellé, dates, quantité, prix estimé, prix confirmé |
| `Destination` | Destination du catalogue | pays, région, ville, slug, temps de trajet par ville de départ, points forts, description, photo, publiée, mise en avant |
| `PeriodeClimatique` | Climat d'une destination sur une période | début, fin, température moyenne, pluie |
| `Prestataire` | Hébergeur, transporteur, guide ou activités | nom, type, contact, conditions d'annulation, fourchettes de prix des transports |
| `Prestation` | Logement ou activité | type, nom, catégorie, capacité, équipements, accessibilité, durée, âge minimum, envies, photos |
| `TarifParPeriode` | Prix d'une prestation sur une période | début, fin, prix, unité (par nuit ou par personne), séjour minimum |
| `RegleDePrix` | Règles de prix de l'agence | marge (20 %), frais de dossier minimum (100 €) et maximum (200 €) |
| `PageDuSite` | Contenu modifiable d'une page du site | titre, slug, contenu, photos, titre et description pour le référencement, publiée, ordre dans le menu |
| `Utilisateur` | Conseiller ou administrateur du backoffice | e-mail, nom, rôle, mot de passe |
| `JournalIA` | Trace de chaque appel à un agent IA | agent, demande, modèle, entrée, sortie, tokens consommés |

Index sur les champs filtrés : statut et conseiller des demandes, pays et publication des destinations, destination et type des prestations, capacité, et périodes (`destination` ou `prestation`, `debut`, `fin`) des climats et des tarifs.

Après une modification d'entité : `make migration`, relire le fichier créé dans `api/migrations/`, puis `make migrate`.

## Données de démo

`make fixtures` (ou `make db-reset` pour repartir d'une base vide) charge un catalogue fictif, défini dans `api/src/DataFixtures/Donnees/` pour que le PO puisse le relire et le modifier.

| Données | Contenu |
| --- | --- |
| Comptes | `admin@shortcut.test` (administrateur), `lea@shortcut.test` et `hugo@shortcut.test` (conseillers), `client@shortcut.test` (Camille), `julien@shortcut.test` et `sarah@shortcut.test` (clients). Mot de passe : `shortcut` |
| Destinations | 58 destinations dans 58 pays (Europe, Moyen-Orient, Afrique et océan Indien, Asie, Amériques, Océanie), une classe par grande région : climat sur les 12 mois de 2027, description, points forts, types de voyage, zones larges (« Europe », « Méditerranée », « Asie du Sud-Est »…) cherchées par le chatbot, photo. Une quinzaine sont mises en avant sur la carte de l'accueil |
| Prestataires | 44 : hébergeurs, transporteurs (fourchettes de vols depuis Paris, Lyon et Bordeaux vers chaque destination, transferts ou véhicule de circuit), guides et prestataires d'activités |
| Prestations | Environ 200 logements et 130 activités, avec capacité, accessibilité, envies, photos et tarifs par saison |
| Règles de prix | Marge 20 %, frais de dossier de 100 à 200 € |
| Pages du site | Accueil, types de voyages, l'agence, contact et pages légales (modèles « à valider ») |
| Demandes | 8 demandes réparties entre Léa, Hugo et une non attribuée, et entre trois clients. Camille a 3 voyages en cours, le maximum : Santorin (deux versions envoyées après une modification, à comparer sur « Ma proposition »), Marrakech (le client a demandé la confirmation : écran « Confirmer la proposition ») et Costa Rica (en discussion, transmise à un conseiller) |

Chaque destination a des vols depuis les trois villes de départ, au moins un logement pour une famille de quatre, un bloc de 20 places ou plus si elle propose les groupes, une salle de réunion si elle propose l'entreprise, et un logement romantique pour deux si elle propose les noces.

Périodes **volontairement non couvertes**, pour tester le passage au conseiller : aucun tarif en Islande d'octobre à avril, les Cabinas Drake Bay (Costa Rica) fermées en septembre et octobre, et des activités de saison (chiens de traîneau en Laponie de décembre à mars, ski au Tyrol de décembre à avril, trek au Népal hors mousson, croisière dans les fjords de mai à septembre…).

Les photos sont libres de droits (CC0 ou domaine public, crédits dans `api/fixtures/images/CREDITS.md`). Elles sont copiées dans `api/public/uploads/` (non versionné), servies par l'API sous `/uploads/…` et accessibles depuis le site grâce au proxy Vite.

## Sécurité et droits

Les droits sont vérifiés par l'API, jamais par le navigateur (`api/config/packages/security.yaml`).

| Profil | Rôle Symfony | Accès |
| --- | --- | --- |
| Administrateur | `ROLE_ADMIN` (hérite de `ROLE_CONSEILLER`) | Toutes les demandes |
| Conseiller | `ROLE_CONSEILLER` | Les demandes qui lui sont attribuées ; il voit aussi les demandes non attribuées pour les aiguiller (se les attribuer ou les confier à un autre conseiller), mais ne les traite qu'une fois attribuées |
| Chatbot (client anonyme) | `ROLE_CHATBOT` | Uniquement la demande de sa propre conversation |

- **Voter** : `DemandeVoter` (`DEMANDE_VOIR`, `DEMANDE_MODIFIER`, `DEMANDE_ATTRIBUER` pour l'aiguillage) s'applique à une demande, à sa conversation et à ses propositions. Dans un contrôleur : `$this->denyAccessUnlessGranted(DemandeVoter::VOIR, $demande)`.
- **Chatbot** : `POST /api/chat/conversations` ouvre une conversation et renvoie un jeton. Les appels suivants envoient ce jeton dans l'en-tête `X-Conversation-Token` (pare-feu `chatbot`, sans session). Sans jeton valide : 401 ; demande d'un autre client : 403.
- **Limite de débit** (`api/config/packages/rate_limiter.yaml`) : par adresse IP, 20 requêtes par minute sur `/api/chat` et 10 nouvelles conversations par heure. Au-delà : 429 avec un message clair et l'en-tête `Retry-After`.
- **Messages du chatbot** : `POST /api/chat` avec le jeton et le corps JSON `{"message": "…"}` (2 000 caractères au plus, sinon 400). La réponse est diffusée en Server-Sent Events (`text/event-stream`) :

  | Événement | Données | Le site |
  | --- | --- | --- |
  | `texte` | `{"texte": "…"}` | ajoute ce morceau à la bulle de l'assistant |
  | `outil` | `{"nom": "chercher_destinations"}` | peut montrer que l'assistant cherche |
  | `demande` | `{"fiche": {…}, "recapitulatifConfirme": false, "langue": "fr"}` | met à jour le récapitulatif ; une fois confirmé, lance la proposition ; `langue` (`fr` ou `en`, US15) règle la langue des textes du chatbot |
  | `combinaison` | `{"destination": {…}, "niveaux": {…}, …}` | la combinaison retenue et ses trois niveaux |
  | `proposition` | `{"jeton": "…", "version": 2}` | une nouvelle version vient d'être envoyée au client : lien vers « Ma proposition » |
  | `erreur` | `{"erreur": "…"}` | affiche le message, déjà rédigé pour le client |
  | `fin` | `{}` | la réponse est complète |

  C'est une requête POST : côté site, lire le flux avec `fetch` et `response.body.getReader()`, pas avec `EventSource` (GET seulement).
- **Agent Conversation** (`api/src/Chatbot`) : Symfony appelle l'API Claude en streaming (`ClientClaude`, client HTTP `claude.client` qui porte la clé), exécute les outils demandés par le modèle (`Chatbot/Outil`, voir [`docs/outils.md`](docs/outils.md)) et rappelle Claude jusqu'à sa réponse finale (8 appels au plus par message). L'historique complet au format de l'API est gardé dans `Conversation::historique`, la conversation lisible dans `Conversation::messages`, chaque appel dans `JournalIA`. Le navigateur ne parle jamais à l'API Claude.
- **Modification après une proposition** (US11, `NouvelleVersion`) : quand le client confirme un changement, la version suivante part de la précédente, qui garde sa combinaison (`Proposition::choix`) et la fiche du moment (`Proposition::exigences`). Dates, durée, voyageurs, budget, ville de départ, températures ou contraintes : la même combinaison est recalculée sans appel à Claude. Autre type, autre zone, autres envies, ou combinaison refusée : l'agent Recherche repart de la précédente. La rédaction écrit la version suivante, envoyée directement au client ; la précédente reste consultable.
- **Page « Ma proposition »** : routes publiques, le lien unique (`jetonPublic`, 64 caractères) tient lieu d'accès. Le lien d'une version remplacée mène à la version qui fait foi (`versionDemandee` indique celle du lien).

  | Route | Effet |
  | --- | --- |
  | `GET /api/propositions/{jeton}` | la proposition : `etat` (`estimee` ou `confirmee`), mention, destination, argumentaire, totaux des niveaux, hypothèses, programme, prestations et leurs points forts, postes (`lignes`, sans prix d'achat), `actions` possibles |
  | `GET /api/propositions/{jeton}/versions/{numero}` | une version reçue par le client, même remplacée (`versions` liste les numéros) |
  | `POST /api/propositions/{jeton}/demande-de-confirmation` | le client fait confirmer l'estimation : la demande passe « à confirmer » |
  | `POST /api/propositions/{jeton}/acceptation` | « J'accepte » sur la version confirmée : proposition et demande « acceptées » |

  Une action impossible renvoie 409 et `{"erreur": "…"}`, déjà rédigé pour le client.
- **Statuts** (`api/config/packages/workflow.yaml`) : deux machines à états du composant Workflow, `proposition` (brouillon, envoyée, confirmée, acceptée, refusée, remplacée) et `demande` (le pipeline : nouvelle, proposition envoyée, à confirmer, confirmée, acceptée, perdue). Les changements de statut passent par leurs transitions (`WorkflowInterface` avec `#[Target('demande')]`). Confirmer dans le backoffice crée une nouvelle version, qui remplace l'estimation (#209, #210).

## API du catalogue

Le catalogue est exposé avec API Platform (ticket #185). La documentation OpenAPI interactive est sur http://localhost:8000/api/docs ; `make openapi` l'exporte dans `docs/openapi.yaml`.

| Ressource | Adresse | Filtres de la liste |
| --- | --- | --- |
| Destinations | `/api/destinations` | `pays`, `ville`, `slug`, `publiee`, `miseEnAvant`, `mois` (AAAA-MM) avec `temperatureMin` / `temperatureMax`, `envie` |
| Prestataires | `/api/prestataires` | `nom`, `type` (`hebergeur`, `transporteur`, `guide`, `activite`) |
| Logements | `/api/logements` | `destination` (IRI), `destinationSlug`, `prestataire`, `categorie`, `capacite[gte]=4`, `chambres[gte]=2`, `accessible`, `envie` (tolère accents et pluriels), `mois` (au moins un tarif dans le mois) |
| Activités | `/api/activites` | les mêmes, plus `ageMinimum[lte]` et `dureeMinutes[lte]` |
| Tarifs | `/api/tarifs` | `prestation` (IRI), `unite`, `mois` |

- **Accès** : lecture, création et modification pour les conseillers connectés (session ouverte par `POST /api/connexion`) ; suppression d'une destination, d'un prestataire ou d'une prestation réservée à l'administrateur. Sans session : 401 ; compte client : 403.
- **Formats** : JSON-LD (`application/ld+json`, avec `totalItems` et la pagination) ou JSON simple (`application/json`). Modification partielle en `PATCH` avec `application/merge-patch+json`.
- **Logements et activités** sont la même entité `Prestation` : l'adresse fixe le type (un champ `type` envoyé est ignoré).
- **Validation** : une entrée invalide renvoie 422 avec `violations`, un message en français par champ (`propertyPath`, `message`), y compris pour une valeur du mauvais type ou un filtre `mois` mal écrit.
- **Données publiques et internes** : groupes de sérialisation `destination:public` / `destination:interne`, `prestation:public` / `prestation:interne`. Les prestataires (contacts) et les tarifs (prix d'achat, avant marge) sont internes. Le site public n'y aura accès qu'en lecture et seulement aux groupes `public` (ticket #216).
- **Montants** en centimes, dates au format `AAAA-MM-JJ`.

## Organisation du site

- `src/routes/` : une route par fichier (TanStack Router). `src/routeTree.gen.ts` est généré automatiquement par le plugin Vite, ne pas le modifier à la main.
- `src/lib/api.ts` : `apiFetch(chemin, schémaZod)` appelle l'API et valide la réponse avec Zod. À utiliser dans les `queryFn` de TanStack Query.
- `src/lib/cn.ts` : combine des classes Tailwind (`clsx` + `tailwind-merge`).
- L'alias `@/` pointe vers `src/`.

## Règles d'équipe

- Pas de push direct sur `main` : pull request et relecture par un autre membre.
- Un ticket est terminé quand ses critères sont vérifiés en local sur `main` à jour, que le code est relu et fusionné, et que le PO l'a accepté.
- Tickets et sprints : [tableau Kanban](https://github.com/users/KJovene/projects/2/views/2).
