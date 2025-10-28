# Anchorless Tech Test Makefile

# Docker lifecycle ---------------------------------------------------------

services-up:
	docker compose up -d --build

services-down:
	docker compose down

service-restart-%:
	docker compose restart $*

app-configure:
	docker compose exec laravel.test php artisan app:configure $(args)

app-boot:
	$(MAKE) services-up
	$(MAKE) app-configure args="$(args)"
	$(MAKE) frontend-build
	docker compose restart react-frontend

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
	docker compose exec react-frontend npm run dev

# Backend utilities --------------------------------------------------------

backend-artisan:
	docker compose exec laravel.test php artisan $(cmd)

backend-composer:
	docker compose exec laravel.test composer $(cmd)

backend-bash:
	docker compose exec laravel.test bash

.PHONY: sync-env services-up services-down service-restart-% app-configure app-boot app-reboot frontend-sh frontend-install frontend-build frontend-dev backend-artisan backend-composer backend-bash
