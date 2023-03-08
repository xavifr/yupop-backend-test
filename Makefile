SHELL := /bin/bash

tests:
	symfony console doctrine:database:drop --force --env=test || true
	symfony console doctrine:database:create --env=test
	symfony console doctrine:migrations:migrate -n --env=test
	symfony console doctrine:fixtures:load -n --env=test
	symfony php bin/phpunit tests

coverage:
	symfony console doctrine:database:drop --force --env=test || true
	symfony console doctrine:database:create --env=test
	symfony console doctrine:migrations:migrate -n --env=test
	symfony console doctrine:fixtures:load -n --env=test
	XDEBUG_MODE=coverage symfony php bin/phpunit tests --coverage-html ./docs/coverage


start_dev:
	symfony composer install
	docker-compose up -d
	sleep 5
	symfony console doctrine:migrations:migrate -n || true
	symfony server:start -d

stop_dev:
	symfony server:stop
	docker-compose down

db:
	docker-compose exec database psql app app

.PHONY: tests coverage start_dev stop_dev
