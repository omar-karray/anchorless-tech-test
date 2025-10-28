# Anchorless Visa Application System - Technical Documentation

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Quick Start](#quick-start)
3. [Project Structure](#project-structure)
4. [Backend API](#backend-api)
5. [Frontend Application](#frontend-application)
6. [Real-time Features](#real-time-features)
7. [Development Workflow](#development-workflow)
8. [Deployment](#deployment)
9. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

### Technology Stack

**Backend:**
- Laravel 12 (PHP 8.3)
- PostgreSQL 17
- Redis 7
- Laravel Reverb (WebSocket Server)
- Laravel Horizon (Queue Management)
- MinIO (S3-compatible Object Storage)
- Laravel Sanctum (Authentication)

**Frontend:**
- React Router v7 with SSR
- TypeScript
- Tailwind CSS
- Laravel Echo + Pusher.js (WebSocket Client)
- Vite (Build Tool)

**Infrastructure:**
- Docker Compose
- Nginx (Reverse Proxy)
- Mailpit (Email Testing)

### System Components

```
┌─────────────────┐         ┌──────────────────┐
│  React Frontend │────────▶│  Laravel API     │
│  (Port 5173)    │◀────────│  (Port 80)       │
└─────────────────┘         └──────────────────┘
         │                           │
         │                           │
         ▼                           ▼
┌─────────────────┐         ┌──────────────────┐
│  Reverb WS      │         │  PostgreSQL      │
│  (Port 8080)    │         │  (Port 5432)     │
└─────────────────┘         └──────────────────┘
                                     │
                            ┌────────┴────────┐
                            │                 │
                            ▼                 ▼
                    ┌──────────────┐  ┌──────────────┐
                    │   Redis      │  │   MinIO      │
                    │  (Port 6379) │  │  (Port 9000) │
                    └──────────────┘  └──────────────┘
```

---

## Quick Start

### Prerequisites
- Docker Desktop or Docker Engine + Docker Compose
- Make (command-line utility)
- Git

### Initial Setup

```bash
# Clone the repository
git clone <repository-url>
cd anchorless-tech-test

# Boot the application (this will set up everything)
make app-boot

# Or with custom seed data
make app-boot args="--seed-demo"
```

This single command will:
1. Start all Docker containers
2. Configure the Laravel backend
3. Run migrations and seeders
4. Install and build the frontend
5. Start all services

**Default credentials:**
- Email: `test@example.com`
- Password: `password`

### Access Points

- **Frontend:** http://localhost:5173
- **Backend API:** http://localhost/api
- **MinIO Console:** http://localhost:9001 (minioadmin/minioadmin)
- **Mailpit:** http://localhost:8025
- **Reverb WebSocket:** ws://localhost:8080

---

## Project Structure

```
anchorless-tech-test/
├── laravel-backend-api/          # Backend API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/      # API Controllers
│   │   │   └── Resources/        # JSON Resources
│   │   ├── Models/               # Eloquent Models
│   │   ├── Jobs/                 # Queue Jobs
│   │   ├── Events/               # Broadcast Events
│   │   └── Policies/             # Authorization Policies
│   ├── routes/
│   │   ├── api.php               # API Routes
│   │   ├── web.php               # Web Routes (Broadcasting Auth)
│   │   └── channels.php          # WebSocket Channels
│   └── database/
│       ├── migrations/           # Database Migrations
│       └── seeders/              # Database Seeders
│
├── react-router-frontend-app/    # Frontend Application
│   ├── app/
│   │   ├── routes/               # Route Components
│   │   │   ├── dashboard.tsx              # Dashboard Layout
│   │   │   ├── dashboard.index.tsx        # Applications List
│   │   │   ├── dashboard.new.tsx          # Create Application
│   │   │   ├── dashboard.applications.$id.edit.tsx   # Edit (Draft)
│   │   │   └── dashboard.application.tsx  # View (Submitted)
│   │   ├── lib/
│   │   │   ├── api-client.ts     # API Helper Functions
│   │   │   └── echo.client.ts    # WebSocket Client
│   │   └── routes.ts             # Route Configuration
│   └── vite.config.ts            # Vite Configuration
│
├── docker-compose.yml            # Docker Services
├── Makefile                      # Development Commands
└── DOCUMENTATION.md              # This file
```

---

## Backend API

### Authentication

All API endpoints (except login/register) require authentication using Laravel Sanctum.

**Session-based (SPA):**
```bash
# Get CSRF token
GET /sanctum/csrf-cookie

# Login
POST /login
{
  "email": "test@example.com",
  "password": "password"
}

# API calls automatically authenticated via session cookie
```

**Token-based:**
```bash
POST /api/auth/token/create
{
  "email": "test@example.com",
  "password": "password"
}

# Use returned token in header
Authorization: Bearer {token}
```

### API Endpoints

#### Visa Applications

```bash
# List all user's applications
GET /api/visa-applications

# Get specific application
GET /api/visa-applications/{id}

# Create new application
POST /api/visa-applications
{
  "country": "PG"  // PG=Portugal, SP=Spain, IT=Italy
}

# Update application (including submit)
PUT /api/visa-applications/{id}
{
  "status": "submitted"  // Changes status and sets submitted_at
}

# Delete application
DELETE /api/visa-applications/{id}
```

#### File Categories

```bash
# List all file categories
GET /api/file-categories

Response:
[
  {
    "id": 1,
    "name": "Visa Application Form",
    "slug": "visa_application_form",
    "description": "Official visa application form"
  },
  ...
]
```

#### Files

```bash
# List files for an application
GET /api/visa-applications/{id}/files

# Upload file (multipart/form-data)
POST /api/visa-applications/{id}/files
{
  "file": <binary>,
  "file_category_id": 1
}

# Delete file
DELETE /api/visa-applications/{id}/files/{fileId}
```

### Queue Jobs

File uploads are processed asynchronously using Laravel Horizon:

1. **StoreVisaApplicantFile Job**
   - Validates file type and size
   - Stores file in MinIO
   - Creates database record
   - Broadcasts success/failure event

**Access Horizon Dashboard:**
```bash
make backend-artisan cmd="horizon:status"
# Or visit: http://localhost/horizon (when authenticated)
```

### WebSocket Broadcasting

#### Events

**VisaApplicantFileStored**
- Channel: `private-visa-applications.{id}`
- Triggered: When file upload job completes successfully
- Payload:
  ```json
  {
    "file": {
      "id": 8,
      "original_name": "document.pdf",
      "category": {
        "id": 2,
        "name": "ID Photo"
      }
    }
  }
  ```

**VisaApplicantFileFailed**
- Channel: `private-visa-applications.{id}`
- Triggered: When file upload job fails
- Payload:
  ```json
  {
    "reason": "Error message"
  }
  ```

#### Channel Authorization

Private channels use policy-based authorization:

```php
// routes/channels.php
Broadcast::channel('visa-applications.{visaApplicationId}', 
  function ($user, $visaApplicationId) {
    $visaApplication = VisaApplication::find($visaApplicationId);
    return $user->can('view', $visaApplication);
  }
);
```

---

## Frontend Application

### Routing Structure

```
/                           → Login/Landing
/login                      → Login Page
/dashboard                  → Applications List
/dashboard/new              → Create New Application
/dashboard/applications/:id/edit  → Edit Draft Application
/dashboard/applications/:id       → View Submitted Application
```

### Key Components

#### 1. Dashboard Index (`dashboard.index.tsx`)
- Lists all user's visa applications
- Shows status badges (draft/submitted)
- Edit button for drafts, View button for all
- Create new application button

#### 2. Create Application (`dashboard.new.tsx`)
- Simple form to select country
- Creates draft application
- Redirects to edit page

#### 3. Edit Application (`dashboard.applications.$id.edit.tsx`)
**Features:**
- Header with country/status badges
- Submit button (enabled when all categories have files)
- Delete button with confirmation modal
- 2-column grid for file categories
- Drag & drop file upload
- Auto-upload on file selection
- Per-category loading spinners
- Real-time WebSocket notifications
- Success messages inside categories
- Individual file delete buttons

**State Management:**
```typescript
const [uploadingCategories, setUploadingCategories] = useState<Set<number>>(new Set());
const [successMessage, setSuccessMessage] = useState<string | null>(null);
const [successCategory, setSuccessCategory] = useState<number | null>(null);
const [showDeleteModal, setShowDeleteModal] = useState(false);
const [showSubmitModal, setShowSubmitModal] = useState(false);
```

#### 4. View Application (`dashboard.application.tsx`)
- Read-only view with same layout as edit
- Shows all documents grouped by category
- No edit capabilities
- Green status badge for submitted applications

### WebSocket Integration

#### Echo Client Setup (`echo.client.ts`)

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const ensureEchoInstance = async (): Promise<Echo | null> => {
  if (window.__anchorlessEcho) {
    return window.__anchorlessEcho;
  }

  const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel, options) => ({
      authorize: (socketId, callback) => {
        fetch(`${laravelBaseUrl}/broadcasting/auth`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
          },
          credentials: 'include',
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
        .then(response => response.json())
        .then(data => callback(null, data))
        .catch(error => callback(error, null));
      }
    }),
  });

  window.__anchorlessEcho = echo;
  return echo;
};
```

#### Usage in Components

```typescript
useEffect(() => {
  let channel: any = null;

  ensureEchoInstance().then((echo) => {
    if (!echo) return;

    channel = echo.private(`visa-applications.${application.id}`);

    channel.listen('VisaApplicantFileStored', (event: any) => {
      showSuccess(`File "${event.file.original_name}" uploaded successfully!`, 
                  event.file.category.id);
      setUploadingCategories(prev => {
        const next = new Set(prev);
        next.delete(event.file.category.id);
        return next;
      });
      revalidator.revalidate();
    });

    channel.listen('VisaApplicantFileFailed', (event: any) => {
      setUploadError(`Upload failed: ${event.reason}`);
      if (event.file?.category?.id) {
        setUploadingCategories(prev => {
          const next = new Set(prev);
          next.delete(event.file.category.id);
          return next;
        });
      }
    });
  });

  return () => {
    ensureEchoInstance().then((echo) => {
      if (echo && channel) {
        echo.leave(`visa-applications.${application.id}`);
      }
    });
  };
}, [application.id, revalidator]);
```

### File Upload Flow

1. User selects file or drags & drops
2. `handleFileSelect` immediately uploads via API
3. Loading spinner shows for that category
4. Backend queues `StoreVisaApplicantFile` job
5. Horizon processes job asynchronously
6. Job broadcasts `VisaApplicantFileStored` event
7. Frontend receives WebSocket event
8. Success message appears in category
9. Loading spinner disappears
10. File list refreshes automatically

---

## Development Workflow

### Makefile Commands

#### Application Lifecycle
```bash
# Start all services and configure
make app-boot

# Start with demo data
make app-boot args="--seed-demo"

# Stop all services
make services-down

# Restart specific service
make service-restart-laravel.test
make service-restart-reverb

# Full reset (destroys all data)
make app-reboot
```

#### Backend Development
```bash
# Access Laravel shell
make backend-bash

# Run artisan commands
make backend-artisan cmd="migrate:fresh --seed"
make backend-artisan cmd="queue:work"
make backend-artisan cmd="reverb:start"

# Run composer commands
make backend-composer cmd="install"
make backend-composer cmd="require vendor/package"
```

#### Frontend Development
```bash
# Access frontend shell
make frontend-sh

# Install dependencies
make frontend-install

# Build production assets
make frontend-build

# Run development server (inside container)
make frontend-dev
```

### Local Development Without Docker

**Backend:**
```bash
cd laravel-backend-api
composer install
php artisan serve
php artisan queue:work
php artisan reverb:start
```

**Frontend:**
```bash
cd react-router-frontend-app
npm install
npm run dev
```

### Database Migrations

```bash
# Create new migration
make backend-artisan cmd="make:migration create_table_name"

# Run migrations
make backend-artisan cmd="migrate"

# Rollback last migration
make backend-artisan cmd="migrate:rollback"

# Fresh migration with seeding
make backend-artisan cmd="migrate:fresh --seed"
```

### Testing

```bash
# Run backend tests
make backend-artisan cmd="test"

# Run specific test
make backend-artisan cmd="test --filter=VisaApplicationTest"

# Frontend tests
make frontend-sh
npm test
```

---

## Deployment

### Production Environment Variables

**Backend (`.env`):**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

REVERB_HOST=wss://yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https

AWS_ACCESS_KEY_ID=your-s3-key
AWS_SECRET_ACCESS_KEY=your-s3-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

**Frontend (`.env`):**
```env
VITE_API_BASE_URL=https://yourdomain.com/api
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=yourdomain.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

### Build for Production

```bash
# Build frontend
make frontend-build

# Optimize backend
make backend-artisan cmd="optimize"
make backend-artisan cmd="route:cache"
make backend-artisan cmd="config:cache"
make backend-artisan cmd="view:cache"
```

### Deployment Checklist

- [ ] Update environment variables
- [ ] Run migrations on production database
- [ ] Build frontend assets
- [ ] Configure web server (Nginx/Apache)
- [ ] Set up SSL certificates
- [ ] Configure WebSocket server (Reverb)
- [ ] Set up queue workers (Supervisor)
- [ ] Configure file storage (S3 or equivalent)
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy

---

## Troubleshooting

### Common Issues

#### 1. WebSocket Connection Failed

**Symptoms:** File upload completes but no success notification

**Solutions:**
```bash
# Check Reverb is running
docker compose ps reverb

# Check Reverb logs
docker compose logs reverb -f

# Restart Reverb
make service-restart-reverb

# Verify .env variables
grep REVERB laravel-backend-api/.env
```

#### 2. File Upload Fails

**Symptoms:** Upload button stuck in loading state

**Solutions:**
```bash
# Check Horizon status
make backend-artisan cmd="horizon:status"

# Check queue workers
docker compose logs horizon -f

# Restart Horizon
make service-restart-horizon

# Check MinIO is accessible
curl http://localhost:9000/minio/health/live
```

#### 3. CORS Errors

**Symptoms:** API requests blocked by browser

**Solutions:**
Check `laravel-backend-api/config/cors.php`:
```php
'paths' => [
    'api/*',
    'sanctum/csrf-cookie',
    'login',
    'logout',
    'broadcasting/auth',
],

'allowed_origins' => [
    'http://localhost:5173',
],
```

#### 4. Authentication Issues

**Symptoms:** 401 Unauthorized on API calls

**Solutions:**
```bash
# Clear session
# In browser console:
document.cookie.split(";").forEach(c => {
  document.cookie = c.replace(/^ +/, "").replace(/=.*/, 
    "=;expires=" + new Date().toUTCString() + ";path=/");
});

# Re-login
# Navigate to /login
```

#### 5. Frontend Build Fails

**Symptoms:** Build errors or blank page

**Solutions:**
```bash
# Clear node modules and reinstall
make frontend-sh
rm -rf node_modules package-lock.json
npm install
npm run build

# Check for TypeScript errors
npm run type-check
```

### Logs

```bash
# All logs
docker compose logs -f

# Specific service
docker compose logs -f laravel.test
docker compose logs -f reverb
docker compose logs -f horizon
docker compose logs -f react-frontend

# Laravel logs
make backend-bash
tail -f storage/logs/laravel.log
```

### Health Checks

```bash
# Backend API
curl http://localhost/api/auth/me

# Reverb WebSocket
curl http://localhost:8080/app/your-app-key

# MinIO
curl http://localhost:9000/minio/health/live

# PostgreSQL
docker compose exec postgres pg_isready

# Redis
docker compose exec redis redis-cli ping
```

---

## Additional Resources

### API Documentation
- Generate with Laravel Scribe: `make backend-artisan cmd="scribe:generate"`
- Access at: http://localhost/docs

### WebSocket Testing
```javascript
// Browser console
const echo = window.__anchorlessEcho;
const channel = echo.private('visa-applications.1');
channel.listen('VisaApplicantFileStored', (e) => console.log(e));
```

### Database Access
```bash
# PostgreSQL CLI
docker compose exec postgres psql -U anchorless -d anchorless_tech_test

# Common queries
SELECT * FROM visa_applications;
SELECT * FROM visa_applicant_files;
SELECT * FROM file_categories;
```

### Performance Monitoring

**Laravel Telescope** (if installed):
```bash
make backend-artisan cmd="telescope:install"
# Access at: http://localhost/telescope
```

**Horizon Dashboard:**
- URL: http://localhost/horizon
- Monitor queues, jobs, and failures

---

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Check GitHub issues
4. Contact the development team

**Version:** 1.0.0  
**Last Updated:** October 28, 2025
