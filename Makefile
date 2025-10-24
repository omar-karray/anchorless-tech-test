pgsql-psql:
	docker compose exec pgsql psql -U sail -d laravel_backend_api
pgsql-bash:
	docker compose exec pgsql bash
# Sync .env from backend to project root
sync-env:
	ln -sf laravel-backend-api/.env .env
# Anchorless Tech Test Makefile

# Docker Compose commands
services-up: sync-env
	docker compose up -d --build

services-down:
	docker compose down

services-restart: sync-env
	docker compose down && docker compose up -d --build

# Restart a specific service by name
service-restart:
	docker compose restart $(service)

service-restart-%:
	docker compose restart $*

# Bring up or down a specific service
service-up:
	docker compose up -d $(service)

service-down:
	docker compose down $(service)

# Backend (Laravel) commands
backend-migrate:
	docker compose exec laravel.test php artisan migrate

backend-artisan:
	docker compose exec laravel.test php artisan $(cmd)

backend-composer:
	docker compose exec laravel.test composer $(cmd)

backend-tinker:
	docker compose exec laravel.test php artisan tinker

backend-bash:
	docker compose exec laravel.test bash

# Frontend (React SSR) commands (add service when available)
frontend-bash:
	docker compose exec react-frontend bash
frontend-sh:
	docker compose exec react-frontend sh

.PHONY: services-up services-down services-restart backend-migrate backend-artisan backend-composer backend-tinker backend-bash
