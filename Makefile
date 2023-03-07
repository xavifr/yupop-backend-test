SHELL := /bin/bash

tests:
	symfony console doctrine:database:drop --force --env=test || true
	symfony console doctrine:database:create --env=test
	symfony console doctrine:migrations:migrate -n --env=test
	symfony console doctrine:fixtures:load -n --env=test
	symfony php bin/phpunit $@

coverage:
	symfony console doctrine:database:drop --force --env=test || true
	symfony console doctrine:database:create --env=test
	symfony console doctrine:migrations:migrate -n --env=test
	symfony console doctrine:fixtures:load -n --env=test
	XDEBUG_MODE=coverage symfony php bin/phpunit tests --coverage-html ./docs/coverage


start_dev:
	symfony server:start -d
	docker-compose up -d

stop_dev:
	symfony server:stop
	docker-compose down

.PHONY: tests start_dev stop_dev
