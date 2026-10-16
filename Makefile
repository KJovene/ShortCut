# ShortCut : commandes du projet. `make` ou `make help` liste tout.
# Tout tourne dans Docker : l'API (PHP, Nginx, PostgreSQL) et le site React (conteneur front).

.DEFAULT_GOAL := help
.PHONY: help install start start-d stop-d site-logs up down stop restart build rebuild ps logs sh psql \
        composer console cc \
        migration migrate fixtures db-reset db-test db-ui schema \
        dev site-sh site-install site-build \
        test test-api test-site lint lint-api lint-site stan fix check

# Les fichiers créés dans le conteneur appartiennent à l'utilisateur de la machine.
export UID := $(shell id -u)
export GID := $(shell id -g)

COMPOSE := docker compose
PHP     := $(COMPOSE) exec php
CONSOLE := $(PHP) php bin/console
NPM     := $(COMPOSE) exec front npm

## —— Aide ————————————————————————————————————————————————
help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z_-]+:.*?## .*$$)|(^## )' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {if ($$1 ~ /^## /) printf "\n\033[33m%s\033[0m\n", substr($$1, 4); else printf "  \033[32m%-14s\033[0m %s\n", $$1, $$2}'
	@echo

## —— Installation ————————————————————————————————————————
install: build up ## Première installation : images, conteneurs, dépendances, base et données de démo
	$(PHP) composer install --no-interaction
	@test -f api/.env.local || cp api/.env.local.example api/.env.local
	$(MAKE) migrate
	$(MAKE) fixtures
	@echo "\nInstallation terminée : renseigner ANTHROPIC_API_KEY dans api/.env.local, puis lancer « make dev »."

## —— Docker ——————————————————————————————————————————————
start: up dev ## Démarre tout puis suit les logs du site

start-d: up ## Démarre tout en arrière-plan (terminal libre)
	@echo "Site : http://localhost:$${FRONT_PORT:-5173} (logs : make site-logs, arrêt : make stop-d)"

stop-d: stop ## Arrête les conteneurs

site-logs: ## Suit les logs du site (conteneur front)
	$(COMPOSE) logs -f front

up: ## Démarre l'API, la base et le site en arrière-plan (http://localhost:8000, http://localhost:5173)
	$(COMPOSE) up -d --wait

down: ## Arrête et supprime les conteneurs (les données de la base sont conservées)
	$(COMPOSE) down

stop: ## Arrête les conteneurs sans les supprimer
	$(COMPOSE) stop

restart: down up ## Redémarre les conteneurs

build: ## Construit les images PHP et Node
	$(COMPOSE) build

rebuild: ## Reconstruit les images sans cache et redémarre
	$(COMPOSE) build --no-cache
	$(MAKE) restart

ps: ## Liste les conteneurs et leur état
	$(COMPOSE) ps

logs: ## Suit les logs (ex. make logs s=front)
	$(COMPOSE) logs -f $(s)

sh: ## Ouvre un shell dans le conteneur PHP
	$(PHP) sh

psql: ## Ouvre psql sur la base de développement
	$(COMPOSE) exec database psql -U shortcut -d shortcut

## —— Symfony —————————————————————————————————————————————
composer: ## Lance Composer (ex. make composer c="require symfony/mailer")
	$(PHP) composer $(c)

console: ## Lance la console Symfony (ex. make console c="debug:router")
	$(CONSOLE) $(c)

cc: ## Vide le cache Symfony
	$(CONSOLE) cache:clear

## —— Base de données —————————————————————————————————————
migration: ## Génère une migration à partir des entités
	$(CONSOLE) make:migration

migrate: ## Applique les migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: ## Charge le jeu de données de démo (vide la base avant)
	$(CONSOLE) doctrine:fixtures:load --no-interaction

db-reset: ## Recrée la base de zéro : suppression, création, migrations, fixtures
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(MAKE) migrate
	$(MAKE) fixtures

db-test: ## Prépare la base de test (shortcut_test)
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration

db-ui: ## Interface web de la base (Adminer) : http://localhost:8080
	$(COMPOSE) up -d adminer
	@echo "Adminer : http://localhost:$${ADMINER_PORT:-8080} (serveur database, utilisateur shortcut, mot de passe shortcut, base shortcut)"

schema: ## Régénère le diagramme de la base (docs/schema.md) depuis les entités Doctrine
	@mkdir -p docs
	$(COMPOSE) exec -T php php bin/console app:schema:diagramme --markdown > docs/schema.md
	@echo "Diagramme écrit dans docs/schema.md"

## —— Site ————————————————————————————————————————————————
dev: ## Suit les logs du site (http://localhost:5173), démarré par make up
	$(COMPOSE) logs -f front

site-sh: ## Ouvre un shell dans le conteneur du site
	$(COMPOSE) exec front sh

site-install: ## Réinstalle les dépendances du site depuis package-lock.json
	$(NPM) ci

site-build: ## Construit le site pour la production
	$(NPM) run build

## —— Qualité —————————————————————————————————————————————
test: test-api test-site ## Lance tous les tests

test-api: ## Tests de l'API (PHPUnit)
	$(PHP) php bin/phpunit

test-site: ## Tests du site (Vitest)
	$(NPM) test

lint: lint-api lint-site ## Vérifie le style et les types partout

lint-api: ## Style de l'API (PHP-CS-Fixer, sans modifier) et conteneur Symfony
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(CONSOLE) lint:container

lint-site: ## Lint, format et types du site
	$(NPM) run lint
	$(NPM) run format:check
	$(NPM) run typecheck

stan: ## Analyse statique de l'API (PHPStan)
	$(PHP) vendor/bin/phpstan analyse

fix: ## Corrige automatiquement le style (PHP-CS-Fixer et Prettier)
	$(PHP) vendor/bin/php-cs-fixer fix
	$(NPM) run format

check: lint stan test site-build ## Tout vérifier avant une pull request
