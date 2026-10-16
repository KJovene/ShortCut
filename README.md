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

## Organisation du site

- `src/routes/` : une route par fichier (TanStack Router). `src/routeTree.gen.ts` est généré automatiquement par le plugin Vite, ne pas le modifier à la main.
- `src/lib/api.ts` : `apiFetch(chemin, schémaZod)` appelle l'API et valide la réponse avec Zod. À utiliser dans les `queryFn` de TanStack Query.
- `src/lib/cn.ts` : combine des classes Tailwind (`clsx` + `tailwind-merge`).
- L'alias `@/` pointe vers `src/`.

## Règles d'équipe

- Pas de push direct sur `main` : pull request et relecture par un autre membre.
- Un ticket est terminé quand ses critères sont vérifiés en local sur `main` à jour, que le code est relu et fusionné, et que le PO l'a accepté.
- Tickets et sprints : [tableau Kanban](https://github.com/users/KJovene/projects/2/views/2).
