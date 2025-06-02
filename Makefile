# Laminas Microscope Development Makefile

.PHONY: help install test test-coverage cs-check cs-fix analyze infection quality clean

# Colors for output
RED=\033[0;31m
GREEN=\033[0;32m
YELLOW=\033[1;33m
BLUE=\033[0;34m
NC=\033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Laminas Microscope Development Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

install: ## Install dependencies
	@echo "$(YELLOW)Installing dependencies...$(NC)"
	composer install --dev

test: ## Run PHPUnit tests
	@echo "$(YELLOW)Running PHPUnit tests...$(NC)"
	vendor/bin/phpunit

test-coverage: ## Run tests with coverage report
	@echo "$(YELLOW)Running tests with coverage...$(NC)"
	vendor/bin/phpunit --coverage-html tests/coverage/html --coverage-clover tests/coverage/clover.xml

test-unit: ## Run only unit tests
	@echo "$(YELLOW)Running unit tests...$(NC)"
	vendor/bin/phpunit tests/Unit

test-integration: ## Run only integration tests
	@echo "$(YELLOW)Running integration tests...$(NC)"
	vendor/bin/phpunit tests/Integration

test-functional: ## Run only functional tests
	@echo "$(YELLOW)Running functional tests...$(NC)"
	vendor/bin/phpunit tests/Functional

cs-check: ## Check coding standards
	@echo "$(YELLOW)Checking coding standards...$(NC)"
	vendor/bin/phpcs

cs-fix: ## Fix coding standards
	@echo "$(YELLOW)Fixing coding standards...$(NC)"
	vendor/bin/phpcbf

analyze: ## Run static analysis with PHPStan
	@echo "$(YELLOW)Running static analysis...$(NC)"
	vendor/bin/phpstan analyse

infection: ## Run mutation testing
	@echo "$(YELLOW)Running mutation testing...$(NC)"
	vendor/bin/infection --threads=4

quality: cs-check analyze test ## Run all quality checks
	@echo "$(GREEN)All quality checks completed!$(NC)"

clean: ## Clean generated files
	@echo "$(YELLOW)Cleaning generated files...$(NC)"
	rm -rf tests/coverage
	rm -rf tests/logs
	rm -rf vendor
	rm -f composer.lock

setup-dev: install ## Setup development environment
	@echo "$(YELLOW)Setting up development environment...$(NC)"
	mkdir -p tests/coverage
	mkdir -p tests/logs
	@echo "$(GREEN)Development environment setup complete!$(NC)"

ci: ## Run CI pipeline locally
	@echo "$(YELLOW)Running CI pipeline...$(NC)"
	make cs-check
	make analyze
	make test-coverage
	@echo "$(GREEN)CI pipeline completed successfully!$(NC)"

watch-tests: ## Watch for file changes and run tests
	@echo "$(YELLOW)Watching for changes...$(NC)"
	@echo "$(BLUE)Note: Requires inotify-tools (install with: apt-get install inotify-tools)$(NC)"
	while inotifywait -r -e modify src/ tests/; do \
		echo "$(YELLOW)Files changed, running tests...$(NC)"; \
		make test; \
	done

docs: ## Generate documentation
	@echo "$(YELLOW)Generating documentation...$(NC)"
	@echo "$(BLUE)Documentation generated in README.md and other .md files$(NC)"

package: ## Create distribution package
	@echo "$(YELLOW)Creating distribution package...$(NC)"
	composer archive --format=zip --dir=dist/

benchmark: ## Run performance benchmarks
	@echo "$(YELLOW)Running performance benchmarks...$(NC)"
	@echo "$(BLUE)Note: This would run performance tests on the debug suite itself$(NC)"
	# Future: Add benchmark tests

docker-test: ## Run tests in Docker container
	@echo "$(YELLOW)Running tests in Docker...$(NC)"
	docker run --rm -v $(PWD):/app -w /app php:8.1-cli make ci

# Development shortcuts
dev-install: setup-dev ## Alias for setup-dev

dev-test: ## Quick development test (unit tests only)
	@echo "$(YELLOW)Running quick development tests...$(NC)"
	vendor/bin/phpunit tests/Unit --stop-on-failure

dev-fix: cs-fix ## Quick fix for development

# Help is the default target
.DEFAULT_GOAL := help
