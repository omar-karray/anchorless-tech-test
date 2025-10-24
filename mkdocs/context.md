# AnchorLess Technical Assessment — Context

This repository contains a local, containerized workspace for the **VISA Dossier: Upload Feature**.
We implement a clean **Laravel 12 API** backend and a **React Router v7 (ex‑Remix) SSR** frontend, wired together via Docker for a frictionless local dev experience.

---

## 🎯 Scope & Goals
- **Backend (Laravel 12, API‑only)**: upload, list (grouped), and delete files. Persist metadata in DB, store files via Laravel Filesystem (targeting **MinIO** in dev via S3 driver; local disk also supported).
- **Frontend (React Router v7 SSR)**: minimal UI to upload a file, list grouped files, delete a file; show simple previews and provide action feedback.
- **Quality**: validation, error handling, maintainable structure, and clear dev ergonomics.

---

## 🧱 High‑Level Architecture
```
anchorless-tech-test/
├─ laravel-backend-api/         # Laravel 12 API (Sail-enabled)
└─ react-router-frontend-app/   # React Router v7 SSR app (Node runtime)
```

**Runtime services (Docker):**
- `backend`  → PHP‑FPM + queue worker (optional) behind Nginx (via Sail)
- `frontend` → Node SSR server (Express / RRv7 server API)
- `minio`    → S3‑compatible object storage for file uploads (dev)
- `minio-console` → MinIO web UI for inspection
- `mysql` *(optional)* → MySQL for relational storage (can default to SQLite to keep setup ultra‑simple)
- `redis` *(optional)* → queue / cache (not required for this exercise)

> **Note:** The exercise only requires local dev. We keep deployment out of scope.

---

## 🗂️ Data Model (Backend)
**Table:** `visa_files`
- `id` (uuid or big int)
- `original_name` (string)
- `stored_name` (string)
- `mime_type` (string)
- `size_bytes` (integer)
- `category` (enum/string; 3 categories chosen by frontend – e.g. `document`, `photo`, `proof`)
- `disk` (string; e.g. `s3` for MinIO or `local`)
- `path` (string; key within the disk)
- `created_at`, `updated_at`

**Validation:**
- Allowed types → **PDF, PNG, JPG** (`application/pdf`, `image/png`, `image/jpeg`)
- Max size → **4 MB**

**API Endpoints:**
- `POST /api/files` → upload one file + optional `category`
- `GET  /api/files` → list files **grouped by type/category**
- `DELETE /api/files/{id}` → delete file (storage + DB)
- Return proper JSON: data, errors (422/404/500), and consistent shapes.

---

## 🖥️ Frontend (React Router v7 SSR)
**Pages/Routes**
- `/` → upload form + grouped list + delete actions

**Data Router**
- `loader()` → fetch grouped files from `GET /api/files` (SSR)
- `action()` → handle `POST` uploads (form submission → `POST /api/files`)
- `useFetcher()` (or `fetcher.Form`) → handle delete (`DELETE /api/files/:id`)

**UI**
- Minimal HTML form (no drag‑and‑drop)
- Previews: inline thumbnail for images; filename for PDFs
- Feedback: uploading…, success, error

---

## 🐳 Dockerized Local Dev
We use **Docker Compose** to orchestrate:
- Laravel 12 via **Sail** (PHP‑FPM, Nginx, dependencies)
- Node SSR server for React Router
- MinIO (S3‑compatible) for uploads

### Compose Topology (illustrative excerpt)
> The actual `docker-compose.yml` in the repo root will be close to this.

```yaml
version: "3.9"

services:
  # ─────────────── Backend (Laravel 12 via Sail) ───────────────
  backend:
    build:
      context: ./laravel-backend-api
      dockerfile: Dockerfile
    env_file:
      - ./laravel-backend-api/.env
    ports:
      - "8080:80"   # Nginx from Sail (app at http://localhost:8080)
    depends_on:
      - minio
      # - mysql    # optional if you don’t want SQLite
    networks: [appnet]

  # ─────────────── Frontend (React Router SSR) ───────────────
  frontend:
    build:
      context: ./react-router-frontend-app
      dockerfile: Dockerfile
    environment:
      - NODE_ENV=development
      - BACKEND_API_BASE=http://backend
    ports:
      - "5173:5173"  # dev server (if using Vite) or SSR port
    depends_on:
      - backend
    networks: [appnet]

  # ─────────────── MinIO (S3‑compatible) ───────────────
  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      - MINIO_ROOT_USER=minioadmin
      - MINIO_ROOT_PASSWORD=minioadmin
    ports:
      - "9000:9000"  # S3 API endpoint
      - "9001:9001"  # console UI
    volumes:
      - minio_data:/data
    networks: [appnet]

  # mysql:  # optional
  #   image: mysql:8.0
  #   environment:
  #     MYSQL_DATABASE: anchorless
  #     MYSQL_USER: app
  #     MYSQL_PASSWORD: app
  #     MYSQL_ROOT_PASSWORD: root
  #   ports:
  #     - "3306:3306"
  #   volumes:
  #     - mysql_data:/var/lib/mysql
  #   networks: [appnet]

networks:
  appnet:
    driver: bridge

volumes:
  minio_data:
  mysql_data:
```

> **Why MinIO?** It mirrors production S3 semantics (put/get/delete, buckets, signed URLs), while running locally. Laravel’s S3 disk can point to MinIO by setting `AWS_ENDPOINT=http://minio:9000` and `AWS_USE_PATH_STYLE_ENDPOINT=true`.

---

## ⚙️ Backend Setup (Laravel 12)
- New project created with **Laravel 12** and **Sail** enabled
- Suggest defaulting to **SQLite** for speed; toggle to MySQL by uncommenting `mysql` in Compose and updating `.env`

**Key `.env` entries (dev):**
```
APP_URL=http://localhost:8080

# Storage (MinIO)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=uploads
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://minio:9000
```

**Artisan quickstart:**
```
# from repo root
./vendor/bin/sail up -d           # or: docker compose up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan storage:link  # if using local disk too
```

---

## ⚙️ Frontend Setup (React Router v7 SSR)
- Vite (or RSBuild) for dev server + SSR entry
- Server entry exposes a minimal Express handler that uses React Router’s SSR API
- Env: `BACKEND_API_BASE` points to the backend service (e.g., `http://backend`) inside Docker; use `http://localhost:8080` from host

**Dev scripts (example):**
```
# from react-router-frontend-app
npm i
npm run dev          # starts SSR dev server on :5173
```

---

## 🔌 Contract & Examples
**Request** `POST /api/files`
- form‑data: `file` (required), `category` (one of `document|photo|proof`)

**Response** `201 Created`
```json
{
  "data": {
    "id": "...",
    "original_name": "passport.pdf",
    "mime_type": "application/pdf",
    "size_bytes": 123456,
    "category": "document",
    "url": "http://localhost:8080/storage/..."  // or signed S3 URL if needed
  }
}
```

**List** `GET /api/files`
```json
{
  "data": {
    "document": [ { "id": "...", "original_name": "passport.pdf", ... } ],
    "photo":    [ { "id": "...", "original_name": "portrait.jpg", ... } ],
    "proof":    [ { "id": "...", "original_name": "attestation.png", ... } ]
  }
}
```

**Delete** `DELETE /api/files/{id}` → `204 No Content`

---

## ✅ Acceptance Checklist
- [ ] Laravel 12 API with upload/list/delete endpoints
- [ ] Validation (PDF/PNG/JPG, ≤ 4 MB), robust error responses
- [ ] DB persistence (SQLite by default; MySQL optional)
- [ ] Files stored via Filesystem (S3 disk to MinIO)
- [ ] React Router v7 SSR: upload form, grouped list (3 categories), delete
- [ ] Basic previews + user feedback
- [ ] Docker Compose up, minimal steps to run both apps
- [ ] Clear README for backend & frontend setup/testing

---

## 📝 Notes for Reviewers
- The implementation favors **clarity and maintainability** (request validation, service layer, resource transformers, repo structure).
- MinIO gives parity with S3 operations likely used in production (AnchorLess uses AWS S3/Textract in their stack).
- The SSR frontend demonstrates data‑router patterns (loader/action/fetcher) and clean API consumption.

---

## 🛠️ MinIO Connection Test Command

A custom Laravel Artisan command is provided to verify MinIO connectivity and file operations:

```
php artisan app:test-fs-connection [--persist]
```
- Writes a test file to the MinIO bucket.
- Checks file existence and lists files in the bucket.
- Deletes the test file unless `--persist` is specified (in which case the file remains for manual inspection).
- Outputs detailed debug info for troubleshooting.

**Usage Example:**
- Quick test (file auto-deleted):
  ```
  php artisan app:test-fs-connection
  ```
- Persist file for manual inspection:
  ```
  php artisan app:test-fs-connection --persist
  ```

This command is useful for validating MinIO setup, permissions, and Laravel filesystem integration during development.
