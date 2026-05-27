# SSDLC Banking Demo — common tasks. Run `make help` for the list.
.DEFAULT_GOAL := help
COMPOSE := docker compose
BACKEND := $(COMPOSE) exec -T backend
# The dev container runs APP_ENV=dev; tests must boot the kernel in the test environment.
BACKEND_TEST := $(COMPOSE) exec -T -e APP_ENV=test backend
.PHONY: help up down logs build ps migrate seed reset jwt-keys db-backup test test-db lint shell-backend shell-frontend

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the whole stack (db + backend + frontend)
	$(COMPOSE) up -d --build
	@echo "Frontend: http://localhost:5173   API: http://localhost:8080/api/health"

down: ## Stop and remove the stack (keeps the database volume)
	$(COMPOSE) down

logs: ## Tail logs from all services
	$(COMPOSE) logs -f

ps: ## Show service status
	$(COMPOSE) ps

migrate: ## Run database migrations inside the backend container
	$(BACKEND) php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Load demo users / accounts / transactions
	$(BACKEND) php bin/console app:load-demo-data

reset: ## Drop schema, re-migrate and re-seed (clean demo state)
	$(BACKEND) php bin/console doctrine:schema:drop --force --full-database
	$(BACKEND) php bin/console doctrine:migrations:migrate --no-interaction
	$(BACKEND) php bin/console app:load-demo-data

jwt-keys: ## (Re)generate the JWT signing keypair
	$(BACKEND) php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

db-backup: ## Dump the database to ./backups (stands in for DO Managed backups / Spaces)
	@mkdir -p backups
	$(COMPOSE) exec -T db pg_dump -U app ssdlc_bank > backups/ssdlc_bank_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup written to ./backups"

test: test-db ## Run backend (PHPUnit) and frontend (svelte-check) tests
	$(BACKEND_TEST) php bin/phpunit
	$(COMPOSE) exec -T frontend npm run check

test-db: ## Create + migrate the isolated test database (ssdlc_bank_test)
	$(BACKEND_TEST) php bin/console doctrine:database:create --if-not-exists -n
	$(BACKEND_TEST) php bin/console doctrine:migrations:migrate -n

lint: ## Run static analysis (PHPStan) + svelte-check
	$(BACKEND) vendor/bin/phpstan analyse --no-progress --memory-limit=512M
	$(COMPOSE) exec -T frontend npm run check

shell-backend: ## Open a shell in the backend container
	$(COMPOSE) exec backend sh

shell-frontend: ## Open a shell in the frontend container
	$(COMPOSE) exec frontend sh
