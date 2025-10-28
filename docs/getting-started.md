# Getting Started

Welcome to the Anchorless Visa Application System! This guide will help you set up and run the application in just a few minutes.

## Prerequisites

Before you begin, ensure you have the following installed on your machine:

- **Docker Desktop** (version 20.10 or higher)
    - [Download for Mac](https://docs.docker.com/desktop/install/mac-install/)
    - [Download for Windows](https://docs.docker.com/desktop/install/windows-install/)
    - [Download for Linux](https://docs.docker.com/desktop/install/linux-install/)
- **Git** - [Download here](https://git-scm.com/downloads)
- **Make** (usually pre-installed on macOS/Linux)
    - Windows users: Install via [Chocolatey](https://chocolatey.org/): `choco install make`

!!! tip "System Requirements"
    - Minimum 8GB RAM
    - 10GB free disk space
    - Docker Desktop must be running before starting the application

## Quick Start (5 Minutes)

### Step 1: Clone the Repository

```bash
git clone https://github.com/omar-karray/anchorless-tech-test.git
cd anchorless-tech-test
```

### Step 2: Start the Application

This single command will set up everything automatically:

```bash
make app-boot
```

!!! success "What happens during `make app-boot`"
    **Phase 1: Environment Setup**
    
    1. ✅ **Copies `.env` files** - Backend and frontend `.env.example` → `.env` (automatic on first run)
    2. ✅ **Starts Docker containers** - Laravel, PostgreSQL, Redis, MinIO, Reverb, Horizon, Frontend
    3. ✅ **Builds images** - Compiles all Docker services with latest code
    
    **Phase 2: Backend Configuration (Interactive)**
    
    4. ✅ **Installs Composer dependencies** - PHP packages for Laravel
    5. ✅ **Configures MinIO** - Prompts: "Configure MinIO now?" → Press `Y`
       - Creates S3-compatible storage bucket
       - Sets up access credentials
    6. ✅ **Runs database migrations** - Prompts: "Run migrate:fresh --seed?" → Press `Y`
       - Creates all database tables
       - Seeds test user (`test@example.com` / `password`)
       - Seeds file categories (Passport, Visa Form, ID Photo, Proof of Address)
    7. ✅ **Runs test suite** - Prompts: "Run tests now?" → Press `N` (optional, can skip)
    
    **Phase 3: Frontend Build**
    
    8. ✅ **Installs npm dependencies** - React Router v7 and all packages
    9. ✅ **Builds production bundle** - Vite compilation (~4 seconds)
    10. ✅ **Restarts frontend container** - Serves the latest build
    
    **Total Time: 3-5 minutes on first run** (faster on subsequent runs)

This process is **fully automated** - just answer `Y` to the prompts (or use `make app-boot args="--yes"` to skip prompts).

### Step 3: Access the Application

Once the setup completes, open your browser:

🌐 **Frontend Application:** [http://localhost:5173](http://localhost:5173)

!!! info "Default Login Credentials"
    - **Email:** `test@example.com`
    - **Password:** `password`

## Additional Access Points

While developing, you may need access to these services:

| Service | URL | Credentials |
|---------|-----|-------------|
| **Frontend App** | http://localhost:5173 | test@example.com / password |
| **Backend API** | http://localhost/api | - |
| **MinIO Console** | http://localhost:9001 | minioadmin / minioadmin |
| **Mailpit (Email Testing)** | http://localhost:8025 | - |
| **WebSocket Server** | ws://localhost:8080 | - |

## Verify Installation

To ensure everything is running correctly:

### 1. Check Docker Containers

```bash
docker compose ps
```

You should see all services in "running" state:

```
NAME                              STATUS
anchorless-tech-test-laravel.test-1    running
anchorless-tech-test-postgres-1        running
anchorless-tech-test-redis-1           running
anchorless-tech-test-minio-1           running
anchorless-tech-test-reverb-1          running
anchorless-tech-test-horizon-1         running
anchorless-tech-test-react-frontend-1  running
```

### 2. Test the API

```bash
curl http://localhost/api/file-categories
```

Expected response: A JSON array of file categories.

### 3. Test the Frontend

1. Navigate to http://localhost:5173
2. You should see the login page
3. Login with default credentials
4. You should see the dashboard

!!! success "Installation Complete!"
    🎉 If all checks pass, you're ready to start developing!

## First Steps After Installation

Now that your application is running, here's what you can do:

### Create Your First Visa Application

1. **Login** at http://localhost:5173 with `test@example.com` / `password`
2. Click **"+ New Application"** button
3. **Select a country** (Portugal, Spain, or Italy)
4. Click **"Create Application"**
5. You'll be redirected to the edit page

### Upload Documents

The application requires 4 types of documents:

1. **Visa Application Form**
2. **ID Photo**
3. **Passport**
4. **Proof of Address**

For each category:

- **Click** the upload zone OR **drag and drop** a file
- Supported formats: PDF, PNG, JPG (max 4MB)
- Files upload automatically when selected
- You'll see a ✅ success notification when processing completes
- Upload happens asynchronously via queue jobs

!!! tip "Real-time Updates"
    File uploads are processed in the background. You'll receive real-time notifications via WebSocket when processing completes!

### Submit Application

Once all 4 categories have at least one file:

1. The **"Submit Application"** button becomes enabled
2. Click it to open confirmation modal
3. Confirm submission
4. Application status changes to **"submitted"**
5. You're redirected to read-only view page

!!! warning "Important"
    Once submitted, you cannot edit or add more documents to the application!

## Next Steps

Now that you have the application running, explore these topics:

- **[User Guide](user-guide.md)** - Learn all features and workflows
- **[Development Guide](development-guide.md)** - Start developing new features
- **[Architecture](backend-architecture.md)** - Understand the system design
- **[API Documentation](api-docs.md)** - Explore available endpoints
- **[Troubleshooting](troubleshooting.md)** - Common issues and solutions

## Managing the Application

### Common Commands

```bash
# Stop all services
make services-down

# Restart the application
make app-boot

# Full reset (destroys all data)
make app-reboot

# Access Laravel container shell
make backend-bash

# Access frontend container shell
make frontend-sh

# Run Laravel artisan commands
make backend-artisan cmd="migrate:fresh --seed"

# View logs
docker compose logs -f
```

### Development with Demo Data

If you want to start with sample applications and files:

```bash
make app-boot args="--seed-demo"
```

This creates several demo applications with uploaded files for testing.

## Stopping the Application

When you're done working:

```bash
make services-down
```

This stops all Docker containers but preserves your data. Next time you run `make app-boot`, your data will still be there.

## Troubleshooting Quick Fixes

### Port Already in Use

If you see errors about ports being in use:

```bash
# Check what's using the port
lsof -i :5173  # Frontend
lsof -i :80    # Backend

# Stop the process or change ports in docker-compose.yml
```

### Docker Not Running

Ensure Docker Desktop is running:

- **Mac:** Look for Docker icon in menu bar
- **Windows:** Look for Docker icon in system tray
- If not running, start Docker Desktop application

### Frontend Not Loading

```bash
# Rebuild frontend
make frontend-build

# Check frontend logs
docker compose logs react-frontend -f
```

### Database Connection Issues

```bash
# Restart database
make service-restart-postgres

# Check database logs
docker compose logs postgres
```

For more detailed troubleshooting, see the [Troubleshooting Guide](troubleshooting.md).

## Getting Help

If you encounter issues:

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Review service logs: `docker compose logs -f`
3. Ensure Docker has enough resources (Settings → Resources)
4. Contact the development team

---

**Ready to dive deeper?** Continue to the [User Guide](user-guide.md) to learn all application features!
