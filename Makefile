#!make

default: help

MAKEFLAGS += --no-print-directory

# ----- Colors -----
GREEN = /bin/echo -e "\x1b[32m\#$1\x1b[0m"

# ----- Programs -----
# Everything runs in the image built from the repository's Dockerfile, so a fresh checkout needs
# nothing but docker. Override PHP_RUN to use a local toolchain instead (this is what the CI does,
# it brings its own PHP):
#     make test PHP_RUN="sh -c"
PHP_VERSION ?= 8.4
IMAGE ?= composer-attribute-collector-dev:$(PHP_VERSION)
COMPOSER_CACHE ?= $(CURDIR)/var/composer
# Xdebug is installed but off, because it slows every CLI run down; the coverage targets turn it on.
XDEBUG_MODE ?= off
DOCKER_RUN = docker run --rm --entrypoint sh --user "$(shell id -u):$(shell id -g)" \
	-e HOME=/tmp -e COMPOSER_HOME=/composer -e XDEBUG_MODE=$(XDEBUG_MODE) \
	-v "$(CURDIR)":/src -v "$(COMPOSER_CACHE)":/composer -w /src $(IMAGE)

ifeq ($(strip $(PHP_RUN)),)
PHP_RUN = $(DOCKER_RUN) -c
IMAGE_DEPENDENCY = image
endif

PHPUNIT = vendor/bin/phpunit

## ----- Help -----
.PHONY: help
help: ## Display this help
	@echo ""
	@$(call GREEN, "Available commands:")
	@echo ""
	@grep -hE '^[a-zA-Z0-9 -]+:.*##' Makefile | sort | while read -r l; do printf "\033[1;32m$$(echo $$l | cut -f 1 -d':')\033[00m:$$(echo $$l | cut -f 3- -d'#')\n"; done
	@echo ""

## ----- Dependencies -----
.PHONY: image
image: ## Build the development image
	@docker build --quiet --build-arg PHP_VERSION=$(PHP_VERSION) --tag $(IMAGE) . > /dev/null

$(COMPOSER_CACHE):
	@mkdir -p $(COMPOSER_CACHE)

vendor: composer.json | $(COMPOSER_CACHE) $(IMAGE_DEPENDENCY)
	@$(PHP_RUN) 'composer install --no-interaction --no-progress'

.PHONY: install
install: vendor ## Install the dev dependencies

.PHONY: update
update: | $(COMPOSER_CACHE) $(IMAGE_DEPENDENCY) ## Update the dev dependencies
	@$(PHP_RUN) 'composer update --no-interaction --no-progress'

## ----- Testing -----
.PHONY: test-dependencies
test-dependencies: vendor test-cleanup

.PHONY: test
test: test-dependencies ## Run the test suite
	@$(PHP_RUN) '$(PHPUNIT)'

.PHONY: test-filter
test-filter: test-dependencies ## Run the tests matching FILTER, e.g. `make test-filter FILTER=testTargetMethods`
	@$(PHP_RUN) '$(PHPUNIT) --filter $(FILTER)'

.PHONY: test-coverage
test-coverage: XDEBUG_MODE = coverage
test-coverage: test-dependencies ## Run the test suite and write an HTML coverage report to build/coverage
	@mkdir -p build/coverage
	@$(PHP_RUN) 'XDEBUG_MODE=coverage $(PHPUNIT) --coverage-html build/coverage'
	@$(call GREEN, "Report written to build/coverage/index.html")

.PHONY: test-coveralls
test-coveralls: XDEBUG_MODE = coverage
test-coveralls: test-dependencies ## Run the test suite and write a clover report to build/logs
	@mkdir -p build/logs
	@$(PHP_RUN) 'XDEBUG_MODE=coverage $(PHPUNIT) --coverage-clover build/logs/clover.xml'

.PHONY: test-cleanup
test-cleanup: ## Wipe the collector cache and the generated sandboxes
	@rm -rf .composer-attribute-collector/*
	@rm -rf tests/sandbox/*

## ----- Quality -----
.PHONY: validate
validate: | $(COMPOSER_CACHE) $(IMAGE_DEPENDENCY) ## Validate composer.json
	@$(PHP_RUN) 'composer validate --no-interaction'

.PHONY: cs
cs: | $(COMPOSER_CACHE) $(IMAGE_DEPENDENCY) ## Check the code style with PHP_CodeSniffer
	@$(PHP_RUN) 'phpcs -s'

.PHONY: stan
stan: vendor ## Analyse the sources with PHPStan (level max)
	@$(PHP_RUN) 'vendor/bin/phpstan --memory-limit=-1'

.PHONY: lint
lint: cs stan ## Run PHP_CodeSniffer and PHPStan

.PHONY: qa
qa: validate lint test ## Run every check, as the CI does

## ----- Utilities -----
.PHONY: shell
shell: | $(COMPOSER_CACHE) $(IMAGE_DEPENDENCY) ## Open a shell in the PHP image
	@$(DOCKER_RUN) -it -c 'exec sh'

.PHONY: clean
clean: test-cleanup ## Remove the dependencies, the caches and the build artefacts
	@rm -rf vendor build var .phpunit.result.cache
