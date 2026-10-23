# ShortCut

Projet réalisé par :

- Cyril Mathé
- Thomas Marcheschi
- Devanadhan Codandabany
- Laurent Dubois
- Kévin Jovené

Site web d'une agence de voyage avec un chatbot qui prépare des propositions sur mesure, et un backoffice pour les conseillers.
Version **v1.0.0**, code gelé le 23/10/2026 sur la branche `dev`.

| Dossier | Contenu                            | Stack                                                                         |
| ------- | ---------------------------------- | ----------------------------------------------------------------------------- |
| `api/`  | API, backoffice et appels à Claude | Symfony 7.4 (PHP 8.3), API Platform, EasyAdmin, Doctrine, PostgreSQL 16       |
| `site/` | Site public et widget du chatbot   | React 19, TypeScript, Vite, TanStack Router, Query et Form, Zod, Tailwind CSS |

## Lancer le projet

Prérequis : Docker (Desktop, ou Engine + Compose v2) et `make`. PHP, Node.js et PostgreSQL tournent dans des conteneurs, rien d'autre à installer. Sous Windows, lancer les commandes depuis Git Bash ou WSL, et lire [Windows : API lente](docs/technique.md#windows--api-lente).

```bash
git clone https://github.com/KJovene/ShortCut.git
cd ShortCut
git switch dev
make install   # images, conteneurs, dépendances, base et données de démo
```

Renseigner ensuite sa clé `ANTHROPIC_API_KEY` dans `api/.env.local`, créé par `make install` : le chatbot en a besoin. Les fichiers `.env.local` ne sont jamais commités.

Les fois suivantes : `make start` pour tout démarrer, `make down` pour tout arrêter (les données de la base sont conservées).

| Adresse                        | Service                                        |
| ------------------------------ | ---------------------------------------------- |
| http://localhost:5173          | Site de l'agence et chatbot                    |
| http://localhost:5173/admin    | Backoffice des conseillers                     |
| http://localhost:8000/api/docs | Documentation interactive de l'API             |
| http://localhost:8080          | Adminer, pour consulter la base (`make db-ui`) |

Comptes de démo, mot de passe `shortcut` : `admin@shortcut.test` (administrateur), `lea@shortcut.test` (conseillère), `client@shortcut.test` (cliente).

## Commandes utiles

| Commande                   | Effet                                                                      |
| -------------------------- | -------------------------------------------------------------------------- |
| `make`                     | Liste toutes les commandes                                                 |
| `make db-reset`            | Recrée la base avec les données de démo                                    |
| `make check`               | Style, types, PHPStan, tests et build : à lancer avant chaque pull request |
| `make fix`                 | Corrige le style automatiquement                                           |
| `make logs s=php`          | Logs d'un service (`php`, `nginx`, `database`, `front`)                    |
| `make sh` / `make site-sh` | Shell dans le conteneur de l'API / du site                                 |

## Où trouver l'information

| Je cherche                                                            | Où                                                                                      |
| --------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Le besoin, le périmètre, les choix et leur justification              | [`cahier-des-charges.md`](cahier-des-charges.md) (registre des décisions en section 13) |
| Les tickets, les sprints, qui fait quoi                               | [Tableau Kanban](https://github.com/users/KJovene/projects/2/views/2)                   |
| L'historique et l'organisation du projet                              | [`docs/gestion-de-projet.md`](docs/gestion-de-projet.md)                                |
| Le détail technique : modèle de données, sécurité, chatbot, API, site | [`docs/technique.md`](docs/technique.md)                                                |
| Le schéma de la base                                                  | [`docs/schema.md`](docs/schema.md)                                                      |
| Les routes de l'API                                                   | [`docs/openapi.yaml`](docs/openapi.yaml) ou http://localhost:8000/api/docs              |
| Le calcul des prix                                                    | [`docs/chiffrage.md`](docs/chiffrage.md)                                                |
| Les outils et les consignes des agents IA                             | [`docs/outils.md`](docs/outils.md) et [`api/prompts/`](api/prompts/)                    |

## Travailler sur le projet

1. Prendre un ticket dans la colonne « À faire » du Kanban et le passer « En cours ».
2. Créer une branche depuis `dev` : `feature/<sujet>` ou `fix/<sujet>`.
3. Lancer `make check`, puis ouvrir une pull request vers `dev` et passer le ticket « En relecture ».
4. Un autre membre relit et fusionne. Le ticket est terminé quand ses critères sont vérifiés en local sur `dev` à jour et que le PO l'a accepté.

Pas de push direct sur `dev` ni sur `main`. Toute décision qui change le périmètre s'ajoute au registre du cahier des charges.
