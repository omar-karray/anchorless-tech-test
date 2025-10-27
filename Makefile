# Anchorless Tech Test Makefile

# Link backend .env into project root so other tools can reference it.
sync-env:
	ln -sf laravel-backend-api/.env .env

# Docker lifecycle ---------------------------------------------------------

services-up: sync-env
	docker compose up -d --build

services-down:
	docker compose down

service-restart-%:
	docker compose restart $*

app-configure: sync-env
	docker compose exec laravel.test php artisan app:configure $(args)

app-boot:
	$(MAKE) services-up
	$(MAKE) app-configure args="$(args)"

app-reboot:
	docker compose down -v --remove-orphans
	$(MAKE) app-boot args="$(args)"

# Backend utilities --------------------------------------------------------

backend-artisan:
	docker compose exec laravel.test php artisan $(cmd)

backend-composer:
	docker compose exec laravel.test composer $(cmd)

backend-bash:
	docker compose exec laravel.test bash

.PHONY: sync-env services-up services-down service-restart-% app-configure app-boot app-reboot backend-artisan backend-composer backend-bash
