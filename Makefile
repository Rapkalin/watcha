# Watcha — developer shortcuts.
# Most targets run on the host; the docker-* targets drive the local Docker stack.

DC = docker compose
PHP = php
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help install up down logs shell db-create migrate fixtures-admin sync scan sass \
        test stan cs cs-fix quality cache-clear

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## --- Docker stack ---
up: ## Build and start the local Docker stack (app on http://localhost:8080)
	$(DC) up -d --build

down: ## Stop the stack
	$(DC) down

logs: ## Tail the stack logs
	$(DC) logs -f

shell: ## Open a shell in the PHP container
	$(DC) exec php sh

## --- Application ---
install: ## Install Composer dependencies
	composer install

db-create: ## Create the database
	$(CONSOLE) doctrine:database:create --if-not-exists

migrate: ## Run database migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

fixtures-admin: ## Create the first admin (override with EMAIL=... PASSWORD=...)
	$(CONSOLE) app:user:create --email=$(or $(EMAIL),admin@watcha.test) --password=$(or $(PASSWORD),AdminPassw0rd!) --role=admin

sync: ## Fetch the latest CVEs/advisories from the providers
	$(CONSOLE) app:cve:sync

scan: ## Scan every monitored site and (re)evaluate alerts
	$(CONSOLE) app:sites:scan

sass: ## Compile the SCSS once
	$(CONSOLE) sass:build

cache-clear: ## Clear the Symfony cache
	$(CONSOLE) cache:clear

## --- Quality & tests ---
test: ## Run the test suite
	$(PHP) bin/phpunit

stan: ## Run static analysis
	vendor/bin/phpstan analyse --no-progress --memory-limit=1G

cs: ## Check coding standards
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding standards
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix

quality: cs stan test ## Run cs + stan + tests
