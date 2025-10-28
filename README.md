# Anchorless Tech Test – Visa Application System

![Tests](https://github.com/omar-karray/anchorless-tech-test/actions/workflows/tests.yml/badge.svg)

## Overview

This repository contains a full-stack visa application management system with a Laravel 12 API backend and React Router v7 SSR frontend. The project is fully containerized with Docker Compose and includes automated setup via Makefile commands. Features include real-time file uploads via WebSockets (Laravel Reverb), background job processing (Horizon), and S3-compatible storage (MinIO).

## Quick Start

```bash
git clone https://github.com/omar-karray/anchorless-tech-test.git
cd anchorless-tech-test
make app-boot
```

**That's it!** The command will:
- Start all Docker containers (Laravel, PostgreSQL, Redis, MinIO, Reverb, Horizon, React Frontend)
- Install Composer and npm dependencies
- Configure MinIO storage (press `Y` when prompted)
- Run database migrations and seed test data (press `Y` when prompted)
- Build the React frontend
- Start all services

**First run takes 3-5 minutes.** Subsequent runs are faster.

### First Login

After setup completes, visit **http://localhost:3000** and log in with:

- **Email:** `test@example.com`
- **Password:** `password`

## Access Points

| Service | URL | Description | Credentials |
| --- | --- | --- | --- |
| **Frontend** | http://localhost:3000 | React Router v7 application (production build) | test@example.com / password |
| **API** | http://localhost | Laravel REST API | Same as frontend |
| **Horizon** | http://localhost/horizon | Queue monitoring dashboard | — |
| **MinIO Console** | http://localhost:8900 | S3 storage management | sail / password |
| **Mailpit** | http://localhost:8025 | Email testing UI | — |

WebSocket server runs on `ws://localhost:8080` (Reverb).

!!! note "Development vs Production"
    - **http://localhost:3000** - Production build served by the container (use after `make app-boot`)
    - **http://localhost:5173** - Development server with hot reload (use with `npm run dev`)

## Prerequisites

- **Docker Desktop** (or Docker Engine with Docker Compose)
- **GNU Make** (included on macOS/Linux; Windows users need WSL2)
- **Git**

No PHP, Composer, or Node.js installation required—everything runs in containers.

## Common Commands

| Command | Description |
| --- | --- |
| `make app-boot` | **Complete setup** - starts containers, installs dependencies, configures services, builds frontend |
| `make app-reboot` | **Clean restart** - stops everything, removes volumes, and runs `app-boot` (fresh start) |
| `make services-up` | Start/rebuild Docker containers only (no configuration) |
| `make services-down` | Stop containers but preserve data volumes |
| `make app-configure` | Rerun backend configuration (MinIO, migrations, tests, cache) |
| `make frontend-build` | Rebuild React frontend (after code changes) |
| `make backend-artisan cmd="..."` | Run any Laravel Artisan command |
| `make backend-bash` | Open shell in Laravel container |

**Interactive prompts:** By default, `make app-boot` will ask for confirmation before configuring MinIO and running migrations. To skip prompts, use `make app-boot args="--yes"`.

## Architecture

### Backend (Laravel 12)
- **Authentication:** Laravel Sanctum (cookie-based sessions)
- **WebSockets:** Laravel Reverb on port 8080 for real-time updates
- **Queue Processing:** Horizon with Redis backend
- **Storage:** MinIO (S3-compatible) for file uploads
- **Database:** PostgreSQL
- **Email Testing:** Mailpit

### Frontend (React Router v7)
- **Server-Side Rendering (SSR)** with client-side hydration
- **Real-time Updates:** Echo client with Pusher protocol
- **Features:** Drag & drop file upload, per-category loading states, WebSocket notifications

### Key Features
- ✅ Real-time file upload progress via WebSocket events
- ✅ Background job processing with queue monitoring
- ✅ Drag & drop file uploads with instant feedback
- ✅ Submit validation (requires all 4 document categories)
- ✅ Read-only view for submitted applications
- ✅ Comprehensive authentication with session management

## Development Workflow

### Making Changes

**Backend changes:**
```bash
make backend-artisan cmd="migrate"           # Run migrations
make backend-artisan cmd="db:seed"           # Seed database
make backend-artisan cmd="test"              # Run tests
make backend-bash                             # Access container shell
```

**Frontend changes:**
```bash
make frontend-build                           # Rebuild after code changes
docker compose restart react-frontend         # Restart frontend container
```

### Database & Seeders

- **Test data** is automatically seeded during `make app-boot`
- **Default user:** test@example.com / password
- **File categories:** Passport, Visa Form, ID Photo, Proof of Address
- Manual reseed: `make backend-artisan cmd="db:seed --class=Database\\Seeders\\DatabaseTestSeeder"`

### Real-Time Features

File uploads are processed asynchronously:
1. File uploaded to MinIO via queue job
2. Success/failure events broadcast via Reverb
3. Frontend receives WebSocket updates
4. UI updates in real-time (no page refresh)

Monitor queues: http://localhost/horizon

## Testing

Tests run automatically during `make app-boot`. To run manually:

```bash
make backend-artisan cmd="test"                    # Full test suite
make backend-artisan cmd="test --filter=Auth"      # Specific tests
docker compose exec laravel.test ./vendor/bin/pest # Direct Pest runner
```

Test coverage includes:
- Authentication flows (login, logout, session management)
- Visa application CRUD operations
- File upload queue jobs and broadcasting events
- WebSocket authorization policies

## Documentation

Full documentation is available in the `docs/` directory:

- **[Getting Started](docs/getting-started.md)** - Complete setup guide with troubleshooting
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues and solutions
- **[Technical Documentation](DOCUMENTATION.md)** - Architecture deep dive

To view with MkDocs (optional):
```bash
cd mkdocs
mkdocs serve  # Visit http://127.0.0.1:8000
```

## Troubleshooting

**Containers won't start:**
```bash
docker compose down -v      # Remove all volumes
make app-reboot             # Fresh start
```

**Port already in use:**
```bash
lsof -ti:80 | xargs kill   # Kill process on port 80 (macOS/Linux)
# or change ports in docker-compose.yml
```

**Frontend not updating:**
```bash
make frontend-build                    # Rebuild assets
docker compose restart react-frontend  # Restart container
```

**Database connection issues:**
```bash
docker compose logs pgsql              # Check PostgreSQL logs
make backend-artisan cmd="migrate:fresh --seed"  # Reset database
```

For more issues, see [docs/troubleshooting.md](docs/troubleshooting.md).

## Project Structure

```
anchorless-tech-test/
├── laravel-backend-api/       # Laravel 12 API
│   ├── app/
│   ├── routes/
│   ├── tests/
│   └── docker/                # Docker configuration
├── react-router-frontend-app/ # React Router v7 frontend
│   ├── app/
│   │   ├── routes/           # Page routes
│   │   └── lib/              # API client, auth, WebSocket
│   └── docker/                # Docker configuration
├── docs/                      # MkDocs documentation
├── Makefile                   # Automation commands
└── docker-compose.yml         # Service orchestration
```

## Contributing

1. Create a feature branch from `main`
2. Make changes and add tests
3. Run `make backend-artisan cmd="test"` to verify
4. Rebuild frontend with `make frontend-build`
5. Submit pull request

## License

This project is proprietary and confidential. Unauthorized copying or distribution is prohibited.

---

**Need help?** Check the documentation in `docs/` or contact the development team.
