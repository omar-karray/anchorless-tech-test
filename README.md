# Anchorless Tech Test – Backend Guide

## Overview

This repository contains the Laravel API that powers the Anchorless technical test. The project is containerised (Docker Compose) and ships with Makefile helpers that automate the full bootstrap: installing Composer dependencies, configuring MinIO, refreshing the database, running the test suite, and clearing caches. Horizon processes queued jobs, while Laravel Reverb handles real-time broadcasting (file uploads, notifications, etc.).

## Prerequisites

- Docker Desktop (or Docker Engine) with Docker Compose.
- GNU Make (ships with macOS/Linux; Windows users can leverage WSL2).
- Node.js only if you plan to run the React frontend (optional).

No global PHP or Composer installation is required—the containers take care of it.

## Installation / First-Time Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/<your-org>/anchorless-tech-test.git
   cd anchorless-tech-test
   ```

2. **Bootstrap everything**
   ```bash
   make app-boot args="--yes --recreate-minio"
   ```

   This command performs the following:

   - Builds and starts the Docker stack (`make services-up`).
   - Runs `php artisan app:configure` inside the Laravel container, which:
     1. Runs `composer install` (idempotent).
     2. Configures MinIO and optionally recreates the bucket.
     3. Executes `migrate:fresh --seed`.
     4. Runs the full test suite.
     5. Clears caches (`optimize:clear`).

   Omit `--yes` if you prefer to confirm each step interactively. Drop `--recreate-minio` if you already trust the bucket state.

3. **Access the stack**
   - API: http://localhost (served by `laravel.test`)
   - Horizon dashboard: http://localhost/horizon (once you configure auth, if necessary)
   - Reverb WebSocket server: ws://localhost:8080
   - Mailpit UI: http://localhost:8025

## Daily Workflow

| Command | Description |
| --- | --- |
| `make services-up` | Start or rebuild the containers without running the configuration workflow. |
| `make app-configure args="--yes"` | Rerun the configure script (composer install, MinIO, migrations, tests, cache clear). |
| `make app-configure args="--yes --recreate-minio"` | Same as above but forces MinIO bucket recreation. |
| `make app-boot args="--yes"` | Full bootstrap without cleaning volumes (useful for fresh workspaces). |
| `make app-reboot args="--yes"` | Stop containers, remove volumes, and run `make app-boot` (clean slate). |
| `make services-down` | Stop and remove the containers, preserving volumes. |
| `make backend-artisan cmd="queue:failed"` | Execute any Artisan command in the Laravel container. |
| `make backend-bash` | Open a shell inside the `laravel.test` container. |
| `make service-restart-reverb` | Restart an individual service (replace `reverb` with any service name). |

All `make` commands accept optional `args="..."` where noted.

## Services & Ports

| Service | Purpose | Ports |
| --- | --- | --- |
| `laravel.test` | Laravel application (PHP-FPM + nginx) | 80 |
| `horizon` | Queue worker supervisor | — |
| `reverb` | WebSocket server (Laravel Reverb) | 8080 |
| `pgsql` | PostgreSQL database | 5432 |
| `redis` | Redis for queues/cache | 6379 |
| `minio` | S3-compatible file storage | 9000 (API), 8900 (console) |
| `mailpit` | SMTP capture & web UI | 1025 (SMTP), 8025 (UI) |
| `react-frontend` | React SSR frontend (optional) | 3000 / 5173 |

## Database & Seeders

- `DatabaseTestSeeder` populates a baseline set of users, visa applications, and files for testing. It runs automatically during `app:configure`.
- To reseed manually: `make backend-artisan cmd="db:seed --class=Database\\Seeders\\DatabaseTestSeeder"`.

## Queues & Real-Time Updates

- Queued jobs are processed via Horizon (`php artisan horizon`) in the dedicated container. File uploads are enqueued, and both success (`VisaApplicantFileStored`) and failure (`VisaApplicantFileFailed`) events broadcast over Reverb to the channel `private-visa-applications.{visaApplicationId}`.
- Ensure the Reverb server is running (`docker compose logs -f reverb`) when testing broadcasting. Clients should connect using the keys defined in `.env` / `.env.example`.

## Tests

The configure workflow already runs the full suite. Re-run as needed:

- `make backend-artisan cmd="test"` – uses Laravel’s `artisan test`.
- `docker compose exec laravel.test ./vendor/bin/pest` – directly run Pest if you prefer.

Feature tests cover authentication, visa applications, and visa applicant file flows, while unit tests assert queued upload behaviour.

## API Documentation

- Source markdown lives under `docs/` (e.g. `docs/api-docs.md`, `docs/index.md`).
- To preview the docs with MkDocs:
  ```bash
  cd docs
  mkdocs serve
  ```
  Then visit http://127.0.0.1:8000 (requires local MkDocs installation).
- When working without MkDocs, read the markdown files directly; they contain request/response examples and endpoint descriptions.

## Troubleshooting & Reset

- `make app-reboot args="--yes"` – complete reset (containers + volumes) followed by a full bootstrap.
- `make services-down` – stop containers but keep volumes (database, MinIO data, etc.).
- Ensure Docker Desktop is running before invoking Make targets.
- For pending Composer issues, run `make backend-composer cmd="install"` to inspect output manually.

This documentation should keep the Anchorless team productive and aligned when onboarding or revisiting the backend. Reach out in the project channel if you hit unexpected issues. Happy coding!
