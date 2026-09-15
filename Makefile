.PHONY: help up down restart logs shell test build lint style verify package clean ps install generate tck tck-diagnostic tck-check
help:
	@echo 'up down restart logs shell install test lint style build verify package generate tck tck-diagnostic tck-check clean ps'
tck-check:
	sh tools/ci-tck.sh
tck:
	sh tools/tck.sh
tck-diagnostic:
	sh tools/tck.sh --diagnostic-core-send-003
up:
	docker compose up -d
down:
	docker compose down
restart:
	docker compose restart
logs:
	docker compose logs -f
shell:
	docker compose run --rm php sh
install:
	docker compose run --rm php composer install
test:
	docker compose run --rm php composer test
lint:
	docker compose run --rm php composer lint
style:
	docker compose run --rm php composer style
verify:
	docker compose run --rm php composer validate --strict
	docker compose run --rm php composer test
	docker compose run --rm php composer lint
	docker compose run --rm php composer style
	docker compose run --rm php composer build
package:
	docker compose run --rm php composer package
build:
	docker compose build
	docker compose run --rm php composer build
generate:
	docker compose run --rm php sh tools/generate.sh
clean:
	docker compose down --remove-orphans
ps:
	docker compose ps
