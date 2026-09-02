SHELL := /bin/sh

.PHONY: setup up down logs migrate test lint build shell-api shell-web e2e

setup:
	cd apps/api && composer install --no-interaction
	cd apps/web && npm ci
	docker compose build
	docker compose up -d --wait mysql redis
	docker compose run --rm api php artisan migrate --isolated --force
	docker compose up -d --wait

up:
	docker compose up -d --wait

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

migrate:
	docker compose exec -T api php artisan migrate --isolated --force

test:
	docker compose -f docker-compose.dependencies.yml up -d --wait
	cd apps/api && php artisan test
	cd apps/web && npm run test -- --run

lint:
	cd apps/api && vendor/bin/pint --test && vendor/bin/phpstan analyse --debug
	cd apps/web && npm run lint && npm run typecheck

build:
	cd apps/web && npm run build
	docker compose build

shell-api:
	docker compose exec api sh

shell-web:
	docker compose exec web sh

e2e:
	cd apps/web && npm run test:e2e
