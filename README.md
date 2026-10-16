# ShortCut

Site web d'une agence de voyage avec un chatbot qui prépare des propositions sur mesure, et un backoffice pour les conseillers.
Le cahier des charges est dans [`cahier-des-charges.md`](cahier-des-charges.md).

| Dossier | Contenu | Stack |
| --- | --- | --- |
| `api/` | API, backoffice et appels à Claude | Symfony 7.4 (PHP 8.3), API Platform, EasyAdmin, Doctrine, PostgreSQL 16 |
| `site/` | Site public et widget du chatbot | React 19, TypeScript, Vite, TanStack Router, TanStack Query, TanStack Form, Zod, Tailwind CSS |

## Prérequis

- Docker Desktop (ou Docker Engine + Compose v2) : l'API PHP 8.3, Nginx et PostgreSQL 16 tournent dans des conteneurs
- Node.js 22.22 ou plus récent (24 conseillé) et npm : le site tourne sur la machine
- `make` (déjà présent sur macOS et Linux)

Pas besoin d'installer PHP, Composer ni PostgreSQL sur sa machine.

## Installation

```bash
git clone https://github.com/KJovene/ShortCut.git
cd ShortCut
make install
```

`make install` construit l'image PHP, démarre les conteneurs, installe les dépendances (Composer dans Docker, npm sur la machine), crée `api/.env.local` à partir de l'exemple et applique les migrations.
Il reste à renseigner sa clé `ANTHROPIC_API_KEY` dans `api/.env.local`.

Les fichiers `.env.local` contiennent les secrets de chacun : ils ne sont **jamais commités**. La clé API Claude n'existe que côté API, le site ne la voit jamais.

## Lancer le projet

```bash
make up     # API + base en arrière-plan
make dev    # site (garde le terminal ouvert)
```

`make start` enchaîne les deux. `make start-d` lance l'API et le site en arrière-plan pour garder le terminal libre : `make site-logs` affiche les logs du site, `make stop-d` arrête tout. `make down` arrête et supprime les conteneurs. Les données de la base sont conservées dans un volume Docker.

| Adresse | Service |
| --- | --- |
| http://localhost:5173 | Site React. En développement, les devtools TanStack Query et Router s'affichent en bas de l'écran. |
| http://localhost:8000/api | Documentation de l'API (API Platform) |
| `localhost:5433` | PostgreSQL (`shortcut` / `shortcut`, base `shortcut`), pour un client SQL. `make psql` ouvre une console. |

Le site appelle l'API en relatif (`/api/...`) : Vite redirige ces requêtes vers l'API, donc pas d'erreur CORS. NelmioCorsBundle autorise aussi `localhost` pour les appels directs.

Ports déjà pris sur votre machine ? Copier `.env.example` en `.env` à la racine et changer `API_PORT` ou `POSTGRES_PORT`. Si `API_PORT` change, mettre la même valeur dans `site/.env.local` (`API_PROXY_TARGET`, voir `site/.env.example`).

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
| `make sh` | Shell dans le conteneur PHP |
| `make logs s=php` | Logs d'un service (`php`, `nginx`, `database`) |

## Qualité du code

```bash
make check   # tout vérifier avant une pull request
make fix     # corriger automatiquement le style (PHP-CS-Fixer et Prettier)
```

`make check` lance le style et les types (`make lint`), PHPStan (`make stan`), les tests PHPUnit et Vitest (`make test`) et le build du site.
Avant les premiers tests qui utilisent la base, préparer la base de test avec `make db-test`.

## Modèle de données

Les entités sont dans `api/src/Entity`, les enums dans `api/src/Enum`. Conventions :

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

## Organisation du site

- `src/routes/` : une route par fichier (TanStack Router). `src/routeTree.gen.ts` est généré automatiquement par le plugin Vite, ne pas le modifier à la main.
- `src/lib/api.ts` : `apiFetch(chemin, schémaZod)` appelle l'API et valide la réponse avec Zod. À utiliser dans les `queryFn` de TanStack Query.
- `src/lib/cn.ts` : combine des classes Tailwind (`clsx` + `tailwind-merge`).
- L'alias `@/` pointe vers `src/`.

## Règles d'équipe

- Pas de push direct sur `main` : pull request et relecture par un autre membre.
- Un ticket est terminé quand ses critères sont vérifiés en local sur `main` à jour, que le code est relu et fusionné, et que le PO l'a accepté.
- Tickets et sprints : [tableau Kanban](https://github.com/users/KJovene/projects/2/views/2).
