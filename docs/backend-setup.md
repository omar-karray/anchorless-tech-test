# Backend Setup & Operations

This guide walks Anchorless engineers through running the Laravel backend locally, resetting the stack, and understanding the supporting infrastructure.

## Prerequisites

- Docker Desktop (or Docker Engine) with Docker Compose.
- GNU Make (installed by default on macOS/Linux; use WSL2 on Windows).
- No global PHP/Composer installation is required—containers provide everything.

## One-Step Bootstrap

Run the helper target from the repository root:

```bash
make app-boot args="--yes --recreate-minio"
```

This command:

1. Builds/starts the Docker stack (`make services-up` → `docker compose up -d --build`).
2. Invokes `php artisan app:configure` inside the container, which:
   - runs `composer install --no-interaction`;
   - configures MinIO (recreates the bucket when `--recreate-minio` is supplied);
   - executes `migrate:fresh --seed`;
   - runs the test suite (`artisan test`);
   - finishes with `artisan optimize:clear`.

Omit `--yes` to answer each prompt interactively.

## Make Targets Cheat Sheet

| Command | Description |
| --- | --- |
| `make services-up` | Start or rebuild the containers without running the configure workflow. |
| `make services-down` | Stop containers (volumes preserved). |
| `make app-boot args="--yes"` | Bootstrap stack + run configure command (no volume cleanup). |
| `make app-reboot args="--yes"` | `docker compose down -v --remove-orphans` then run `make app-boot`—complete clean slate. |
| `make app-configure args="--yes"` | Re-run configure script in-place (Composer, MinIO, migrations, tests, cache clear). |
| `make app-configure args="--yes --recreate-minio"` | Same as above but forces MinIO bucket recreation. |
| `make backend-artisan cmd="queue:failed"` | Execute arbitrary Artisan command. |
| `make backend-bash` | Open an interactive shell inside the `laravel.test` container. |
| `make backend-composer cmd="install"` | Run Composer manually when debugging dependency issues. |
| `make service-restart-reverb` | Restart a single service (substitute any Docker service name). |

All targets accept optional `args="..."` where noted. Commands run relative to the repository root.

## Running Services

| Service | Purpose | Local Ports |
| --- | --- | --- |
| `laravel.test` | PHP-FPM + nginx; serves API | 80 |
| `horizon` | Queue worker supervisor | — |
| `reverb` | Laravel Reverb WebSocket server | 8080 |
| `pgsql` | PostgreSQL database | 5432 |
| `redis` | Queue/cache backend | 6379 |
| `minio` | S3-compatible object store | 9000 (API), 8900 (console) |
| `mailpit` | SMTP capture & web UI | 1025 (SMTP), 8025 (UI) |
| `react-frontend` | React SSR dev server (optional) | 3000 / 5173 |

Horizon and Reverb start automatically when you bring up the stack.

## Environment Notes

- `laravel-backend-api/.env.example` is copied to `.env` at container start if the file is missing. Adjust `.env` once and it will be reused on subsequent boots.
- `.env` already contains Reverb credentials. Frontend clients connect via `ws://localhost:8080` (development) using the `REVERB_APP_KEY`.
- MinIO credentials default to `sail` / `password`. The bucket is named `uploads`.

## Testing & Seed Data

- The configure workflow seeds demo data via `DatabaseTestSeeder`.
- Re-run manually with `make backend-artisan cmd="db:seed --class=Database\\Seeders\\DatabaseTestSeeder"`.
- Execute tests any time with `make backend-artisan cmd="test"` or `docker compose exec laravel.test ./vendor/bin/pest`.

## Queues & Broadcasting

- File uploads are queued (`StoreVisaApplicantFile` job) and processed by Horizon. When the job finishes, two outcomes are broadcast:
  - `VisaApplicantFileStored` (`status: stored` + payload);
  - `VisaApplicantFileFailed` (`status: failed` + reason).
- Both events publish to the private channel `visa-applications.{visaApplicationId}`. Subscribe with Laravel Echo or ad-hoc clients (e.g. `npx wscat --connect 'ws://localhost:8080/app/<key>?protocol=7&client=js'`).

## Troubleshooting

- Use `make app-reboot args="--yes"` if you need to nuke containers and volumes.
- Ensure Docker Desktop is running before invoking make targets.
- If Composer fails during `app:configure`, inspect the output with `make backend-composer cmd="install"`.
- Tail Horizon / Reverb logs as needed:
  ```bash
  docker compose logs -f horizon
  docker compose logs -f reverb
  ```

## API Reference

See [API Documentation](api-docs.md) for endpoints, payloads, and examples. High-level architecture, context, and data model details live in their respective sections within the docs navigation.
