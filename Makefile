.PHONY: help up down install test coverage analyse cs cs-fix ci smoke logs shell

DC = docker compose
RUN = $(DC) run --rm api

help: ## Lista os comandos disponíveis
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

up: install ## Sobe API (8080), mock SOAP (8081) e PostgreSQL (5432)
	$(DC) up -d --wait

down: ## Derruba os containers
	$(DC) down

install: ## Instala as dependências
	$(DC) build
	$(RUN) --no-deps composer install

test: ## Roda todos os testes (unitários, integração e banco)
	$(RUN) composer test

coverage: ## Testes com relatório de cobertura
	$(RUN) composer test:coverage

analyse: ## Análise estática (PHPStan nível max)
	$(RUN) --no-deps composer analyse

cs: ## Verifica o estilo de código
	$(RUN) --no-deps composer cs

cs-fix: ## Corrige o estilo de código
	$(RUN) --no-deps composer cs:fix

ci: ## Pipeline completo (estilo + análise + testes)
	$(RUN) composer ci

smoke: ## Teste de fumaça contra a API no ar (requer make up)
	sh tests/smoke.sh http://localhost:8080

logs: ## Acompanha os logs da API (JSON)
	$(DC) logs -f api

shell: ## Abre um shell no container da API
	$(RUN) sh
