# Anchorless Tech Test Makefile

# Docker lifecycle ---------------------------------------------------------

frontend-host-install:
	cd react-router-frontend-app && npm install

# Start only infrastructure services (DB, Redis, MinIO, Mailpit)
services-infra:
	docker compose up -d --build pgsql redis minio mailpit

# Start Laravel service only (without Horizon/Reverb)
services-laravel:
	docker compose up -d --build laravel.test

# Start Horizon and Reverb (requires composer deps to be installed first)
services-workers:
	docker compose up -d --build horizon reverb

# Start frontend service
services-frontend:
	docker compose up -d --build react-frontend

# Start all services in the correct order
services-up: frontend-host-install
	$(MAKE) services-infra
	@echo "Waiting for infrastructure services to be ready..."
	@sleep 3
	$(MAKE) services-laravel
	@echo "Waiting for Laravel to be ready..."
	@sleep 2

services-down:
	docker compose down

service-restart-%:
	docker compose restart $*

backend-composer-install:
	docker compose exec laravel.test composer install --no-interaction

app-configure: backend-composer-install
	docker compose exec laravel.test php artisan app:configure $(args)

app-boot:
	$(MAKE) services-up
	$(MAKE) app-configure args="$(args)"
	$(MAKE) services-workers
	$(MAKE) services-frontend
	$(MAKE) frontend-build

app-reboot:
	docker compose down -v --remove-orphans
	$(MAKE) app-boot args="$(args)"

# Frontend utilities -------------------------------------------------------

frontend-sh:
	docker compose exec react-frontend sh

frontend-install:
	docker compose exec react-frontend npm install

frontend-build:
	docker compose exec react-frontend npm install
	docker compose exec react-frontend npm run build

frontend-dev:
	docker compose exec react-frontend npm run dev -- --host

# Backend utilities --------------------------------------------------------

backend-artisan:
	docker compose exec laravel.test php artisan $(cmd)

backend-composer:
	docker compose exec laravel.test composer $(cmd)

backend-bash:
	docker compose exec laravel.test bash

.PHONY: sync-env services-up services-down service-restart-% backend-composer-install app-configure app-boot app-reboot frontend-sh frontend-install frontend-build frontend-dev backend-artisan backend-composer backend-bash
