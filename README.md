# ShortCut

Site web d'une agence de voyage avec un chatbot qui prépare des propositions sur mesure, et un backoffice pour les conseillers.
Le cahier des charges est dans [`cahier-des-charges.md`](cahier-des-charges.md).

| Dossier | Contenu | Stack |
| --- | --- | --- |
| `api/` | API, backoffice et appels à Claude | Symfony 7.4 (PHP 8.3), API Platform, EasyAdmin, Doctrine, PostgreSQL 16 |
| `site/` | Site public et widget du chatbot | React 19, TypeScript, Vite, TanStack Router, TanStack Query, TanStack Form, Zod, Tailwind CSS |

## Prérequis

- PHP 8.3 ou plus récent, avec les extensions `intl` et `pdo_pgsql`
- Composer 2
- Node.js 22.22 ou plus récent (24 conseillé) et npm
- PostgreSQL 16 : l'environnement Docker Compose arrive avec le ticket #171. En attendant, l'API démarre sans base tant qu'aucune entité n'est utilisée.
- Facultatif : [Symfony CLI](https://symfony.com/download) pour le serveur de développement

## Installation

```bash
git clone https://github.com/KJovene/ShortCut.git
cd ShortCut

# API
cd api
composer install
cp .env.local.example .env.local   # puis renseigner ANTHROPIC_API_KEY
cd ..

# Site
cd site
npm install
cd ..
```

Les fichiers `.env.local` contiennent les secrets de chacun (clé API Claude, identifiants locaux) : ils ne sont **jamais commités**.
Les valeurs par défaut sont dans `api/.env`. Les exemples à copier sont `api/.env.local.example` et `site/.env.example`.

## Lancer le projet

Dans deux terminaux :

```bash
# Terminal 1 : API sur http://127.0.0.1:8000
cd api
symfony serve --port=8000        # ou : php -S 127.0.0.1:8000 -t public

# Terminal 2 : site sur http://localhost:5173
cd site
npm run dev
```

Le site appelle l'API en relatif (`/api/...`) : Vite redirige ces requêtes vers `http://127.0.0.1:8000`, donc pas de souci de CORS en local.
Si l'API tourne ailleurs, copier `site/.env.example` en `site/.env.local` et changer `API_PROXY_TARGET`.

Vérifications rapides :

- http://127.0.0.1:8000/api : documentation de l'API (API Platform)
- http://localhost:5173 : page d'accueil du site. En développement, les devtools TanStack Query et Router s'affichent en bas de l'écran.

## Qualité du code

À lancer avant chaque pull request.

```bash
# API
cd api
vendor/bin/php-cs-fixer fix          # formate le code (--dry-run pour vérifier seulement)
vendor/bin/phpstan analyse           # analyse statique
php bin/phpunit                      # tests

# Site
cd site
npm run lint                         # ESLint (règles TanStack Query et Router incluses)
npm run format                       # Prettier (format:check pour vérifier seulement)
npm run typecheck                    # TypeScript
npm test                             # Vitest
npm run build                        # build de production
```

## Organisation du site

- `src/routes/` : une route par fichier (TanStack Router). `src/routeTree.gen.ts` est généré automatiquement par le plugin Vite, ne pas le modifier à la main.
- `src/lib/api.ts` : `apiFetch(chemin, schémaZod)` appelle l'API et valide la réponse avec Zod. À utiliser dans les `queryFn` de TanStack Query.
- `src/lib/cn.ts` : combine des classes Tailwind (`clsx` + `tailwind-merge`).
- L'alias `@/` pointe vers `src/`.

## Règles d'équipe

- Pas de push direct sur `main` : pull request et relecture par un autre membre.
- Un ticket est terminé quand ses critères sont vérifiés en local sur `main` à jour, que le code est relu et fusionné, et que le PO l'a accepté.
- Tickets et sprints : [tableau Kanban](https://github.com/users/KJovene/projects/2/views/2).
