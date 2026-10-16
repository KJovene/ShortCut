# ShortCut : commandes du projet. `make` ou `make help` liste tout.
# L'API (PHP, Nginx, PostgreSQL) tourne dans Docker ; le site tourne sur la machine (npm).

.DEFAULT_GOAL := help
.PHONY: help install start start-d stop-d site-logs up down stop restart build rebuild ps logs sh psql \
        composer console cc \
        migration migrate fixtures db-reset db-test \
        dev site-install site-build \
        test test-api test-site lint lint-api lint-site stan fix check

# Les fichiers créés dans le conteneur appartiennent à l'utilisateur de la machine.
export UID := $(shell id -u)
export GID := $(shell id -g)

COMPOSE := docker compose
PHP     := $(COMPOSE) exec php
CONSOLE := $(PHP) php bin/console
NPM     := npm --prefix site
# Site lancé en arrière-plan par `make start-d` : pid et logs, ignorés par git.
RUN_DIR  := .run
SITE_PID := $(RUN_DIR)/site.pid
SITE_LOG := $(RUN_DIR)/site.log

## —— Aide ————————————————————————————————————————————————
help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z_-]+:.*?## .*$$)|(^## )' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {if ($$1 ~ /^## /) printf "\n\033[33m%s\033[0m\n", substr($$1, 4); else printf "  \033[32m%-14s\033[0m %s\n", $$1, $$2}'
	@echo

## —— Installation ————————————————————————————————————————
install: build up ## Première installation : images, conteneurs, dépendances, base et données de démo
	$(PHP) composer install --no-interaction
	@test -f api/.env.local || cp api/.env.local.example api/.env.local
	$(NPM) install
	$(MAKE) migrate
	$(MAKE) fixtures
	@echo "\nInstallation terminée : renseigner ANTHROPIC_API_KEY dans api/.env.local, puis lancer « make dev »."

## —— Docker ——————————————————————————————————————————————
start: up dev ## Démarre l'API puis le site (le site garde le terminal)

start-d: up ## Démarre l'API et le site en arrière-plan (terminal libre)
	@mkdir -p $(RUN_DIR)
	@if [ -f $(SITE_PID) ] && kill -0 $$(cat $(SITE_PID)) 2>/dev/null; then \
		echo "Le site tourne déjà (pid $$(cat $(SITE_PID)))."; \
	else \
		(cd site && exec nohup ./node_modules/.bin/vite > ../$(SITE_LOG) 2>&1) & echo $$! > $(SITE_PID); \
		echo "Site lancé en arrière-plan : http://localhost:5173 (logs : make site-logs, arrêt : make stop-d)"; \
	fi

stop-d: ## Arrête le site lancé en arrière-plan et les conteneurs
	@if [ -f $(SITE_PID) ] && kill $$(cat $(SITE_PID)) 2>/dev/null; then echo "Site arrêté."; fi
	@rm -f $(SITE_PID)
	$(COMPOSE) stop

site-logs: ## Suit les logs du site lancé en arrière-plan
	@tail -f $(SITE_LOG)

up: ## Démarre l'API et la base en arrière-plan (http://localhost:8000)
	$(COMPOSE) up -d --wait

down: ## Arrête et supprime les conteneurs (les données de la base sont conservées)
	$(COMPOSE) down

stop: ## Arrête les conteneurs sans les supprimer
	$(COMPOSE) stop

restart: down up ## Redémarre les conteneurs

build: ## Construit l'image PHP
	$(COMPOSE) build

rebuild: ## Reconstruit l'image PHP sans cache et redémarre
	$(COMPOSE) build --no-cache
	$(MAKE) restart

ps: ## Liste les conteneurs et leur état
	$(COMPOSE) ps

logs: ## Suit les logs (ex. make logs s=php)
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

## —— Site ————————————————————————————————————————————————
dev: ## Démarre le site en développement (http://localhost:5173)
	$(NPM) run dev

site-install: ## Installe les dépendances du site
	$(NPM) install

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
