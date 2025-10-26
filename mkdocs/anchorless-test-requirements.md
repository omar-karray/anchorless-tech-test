

# AnchorLess Technical Assessment — Official Requirements

This document outlines the exact scope and functional requirements for the AnchorLess take-home test.  
**Important:** this version follows the official brief — file uploads go through Laravel (not direct-to-S3).

---

## 🎯 Objective

Build a minimal **VISA Dossier Upload Feature** with:
- A **Laravel 12 (API-only)** backend.
- A **React Router v7 (Remix SSR)** frontend.
- Containerized local dev environment (Docker Compose + Laravel Sail).

The goal is to demonstrate code structure, validation, and integration quality — not UI polish.

---

## 🧱 Laravel Backend (API)

### Core Requirements
- Expose API endpoints to:
  - **POST `/api/files`** → Upload a file.
  - **GET `/api/files`** → List uploaded files, grouped by category/type.
  - **DELETE `/api/files/{id}`** → Delete a file.
- Persist metadata in a **database** (SQLite or MySQL).
- Store physical files via **Laravel Filesystem** (`local` disk is acceptable).

### Validation
- Allowed types: **PDF, PNG, JPG**.
- Max file size: **4 MB**.
- Reject other MIME types with proper 422 JSON errors.
- Use Laravel FormRequest validation and return structured API responses.

### Data Model
**Table:** `visa_files`
| Field | Type | Description |
|--------|------|-------------|
| id | bigint / uuid | Primary key |
| original_name | string | Original filename |
| stored_name | string | Name used in storage |
| mime_type | string | MIME type |
| size_bytes | integer | File size |
| category | string | Category (one of 3 user-defined types) |
| path | string | Storage path |
| disk | string | Filesystem disk name |
| created_at / updated_at | timestamps | Audit fields |

### API Format
- Responses must follow a consistent JSON envelope:
  ```json
  { "data": { ... }, "errors": null }
  ```
  or
  ```json
  { "data": null, "errors": { "message": "Validation failed", "details": { ... } } }
  ```

---

## 💻 React Router Frontend (Remix SSR)

### Core Requirements
- Implement a minimal UI with:
  - File input (no drag & drop).
  - Dropdown or radio to select a **category** (3 categories of your choice).
  - “Upload” button that calls the Laravel API.
  - A list of uploaded files, grouped by category.
  - “Delete” button per file (calls DELETE endpoint).
- Basic file previews:
  - Image thumbnail for PNG/JPG.
  - Filename for PDFs.
- Simple feedback states:
  - Uploading…
  - Success ✅
  - Error ❌

### Technical Notes
- Use **React Router v7 Data APIs**:
  - `action()` for uploads (form submission to Laravel).
  - `loader()` for SSR data fetching (file list).
  - `useFetcher()` for delete actions.
- SSR must render initial file list server-side.
- After actions, trigger revalidation (`revalidator.revalidate()`).

---

## 🧰 Infrastructure & Setup

- Use **Docker Compose** workspace:
  ```
  anchorless-tech-test/
  ├─ laravel-backend-api/
  ├─ react-router-frontend-app/
  └─ docker-compose.yml
  ```
- Laravel backend runs via **Sail**.
- React frontend runs in Node container (SSR).
- Local storage (`storage/app/public`) mapped to host volume for easy file inspection.

---

## ✅ Acceptance Checklist

| Requirement | Done |
|--------------|------|
| Laravel 12 API with upload/list/delete endpoints | ☐ |
| File validation (PDF/PNG/JPG, ≤4 MB) | ☐ |
| DB persistence (SQLite or MySQL) | ☐ |
| Files stored via Laravel Filesystem (local disk) | ☐ |
| React Router v7 SSR frontend (minimal UI) | ☐ |
| Grouped list by 3 categories | ☐ |
| Basic previews + upload/delete feedback | ☐ |
| Clean error handling | ☐ |
| README with setup and test steps | ☐ |

---

## 🧩 Optional Enhancements (if time allows)
- Show progress bar (client-side).
- Handle concurrent uploads gracefully.
- Add simple queue job after upload (for example: generate thumbnail).

---

## ⚙️ Notes

A second branch (“**pro**”) may later implement an advanced version using **Direct-to-Object-Storage Multipart Upload** with MinIO/S3 for production-grade scalability.  
This document, however, represents the **strict scope** of the official AnchorLess test submission.