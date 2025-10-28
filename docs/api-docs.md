# API Documentation

The Anchorless VISA Dossier API is JSON-only and every response is wrapped in the shared envelope:

```json
{ "data": { ... }, "errors": null }
```

Validation or server failures instead return:

```json
{
  "data": null,
  "errors": {
    "message": "Validation failed",
    "details": { "field": ["..."] }
  }
}
```

Most endpoints are guarded by `auth:sanctum` and accept either:
- Cookie session (SPA via Laravel Sanctum's stateful sessions)
- Bearer token (Sanctum Personal Access Token)

See the Authentication section below for both flows.

---

## Visa Applications

### GET `/api/visa-applications`
List the authenticated applicant’s visa applications.
- **Response `200`**
  ```json
  {
    "data": [
      {
        "id": 1,
        "country": "FR",
        "status": "draft",
        "submitted_at": null,
        "created_at": "2025-10-24T12:00:00Z",
        "updated_at": "2025-10-24T12:00:00Z",
        "files": [ { "...file metadata..." } ]
      }
    ],
    "errors": null
  }
  ```

### POST `/api/visa-applications`
Create a new visa application.
- **Body**
  - `country` (string, required, ISO 3166-1 alpha-2)
  - `status` (string, optional, one of `draft`, `submitted`, `approved`, `rejected`)
  - `submitted_at` (ISO 8601 datetime, optional)
- **Response `201`** – newly created application with eager-loaded files list (initially empty).

### GET `/api/visa-applications/{visa_application}`
Fetch a specific visa application that belongs to the authenticated user.
- **Response `200`** – application payload identical to the list response item.
- **Response `403/404`** – returned when the application does not belong to the user or cannot be found.

### PUT `/api/visa-applications/{visa_application}`
Update mutable metadata on an existing application.
- **Body**
  - `country` (string, optional)
  - `status` (string, optional, same enum as above)
  - `submitted_at` (ISO 8601 datetime, nullable)
- **Response `200`** – updated application.

### DELETE `/api/visa-applications/{visa_application}`
Delete an application and any uploaded files associated with it.
- **Response `200`**
  ```json
  { "data": { "deleted": true }, "errors": null }
  ```

---

## Visa Files

### GET `/api/visa-applications/{visa_application}/files`
Return all uploaded files for the specified visa application (the path parameter must belong to the authenticated applicant).
- **Response `200`**
  ```json
  {
    "data": [
      {
        "id": 2,
        "visa_application": { "id": 4, "country": "FR", "status": "submitted" },
        "original_name": "passport.pdf",
        "stored_name": "f23abcd.pdf",
        "mime_type": "application/pdf",
        "size_bytes": 102400,
        "path": "visa-applications/4/files/f23abcd.pdf",
        "disk": "local",
        "category": { "id": 2, "name": "Passport", "slug": "passport" },
        "created_at": "2025-10-24T12:05:00Z"
      }
    ],
    "errors": null
  }
  ```

### POST `/api/visa-applications/{visa_application}/files` (Queue-Based)
Upload a dossier document for a specific visa application. The upload is accepted, stored temporarily, and queued for asynchronous processing; Horizon moves the file to its final location and broadcasts the result over Reverb.

- **Body** (multipart/form-data)
  - `file_category_id` (integer, required, must exist)
  - `file` (required `PDF`, `PNG`, or `JPG`, max 4 MB)
- **Response `202`**
  ```json
  {
    "data": {
      "message": "File upload queued for processing."
    },
    "errors": null
  }
  ```

- **Broadcasts**
  - `VisaApplicantFileStored` on `private-visa-applications.{visaApplicationId}` with payload:
    ```json
    {
      "status": "stored",
      "file": { "...VisaApplicantFileResource..." }
    }
    ```
  - `VisaApplicantFileFailed` on the same channel when the queued job cannot complete:
    ```json
    {
      "status": "failed",
      "reason": "temporary_file_missing"
    }
    ```
  Subscribe via Laravel Echo (Reverb driver) or any Pusher-compatible WebSocket client.

---

## Direct File Upload (Direct-to-Storage)

**Branch:** `multipart-file-upload`

These endpoints enable direct browser-to-MinIO uploads using pre-signed URLs, eliminating the need to proxy files through the Laravel backend. Two strategies are available based on file size:

- **Direct Upload** (< 50MB): Single PUT request with entire file
- **Multipart Upload** (≥ 50MB): File split into 5MB chunks, uploaded in parallel

### POST `/api/visa-applications/{visa_application}/files/direct-upload/initiate`
Request a pre-signed URL for direct upload of files under 50MB.

- **Body** (JSON)
  ```json
  {
    "filename": "passport.pdf",
    "content_type": "application/pdf",
    "file_size": 5242880
  }
  ```
- **Response `200`**
  ```json
  {
    "success": true,
    "data": {
      "file_key": "visa-applications/123/files/2024-10-28_143022_a1b2c3d4.pdf",
      "presigned_url": "https://minio.example.com/bucket/visa-applications/123/...",
      "expiry": "2024-10-28T15:30:22+00:00"
    },
    "message": "Pre-signed URL generated successfully. Upload your file directly to the provided URL."
  }
  ```
- **Validation Errors `422`**
  - `filename` is required (max 255 chars)
  - `content_type` is required (max 100 chars)
  - `file_size` is required, must be 1-52428800 bytes (50MB max)

**Client Flow:**
1. Request pre-signed URL from this endpoint
2. Upload file directly to MinIO using `PUT` request to `presigned_url`
3. Call completion endpoint with `file_key`

### POST `/api/visa-applications/{visa_application}/files/direct-upload/complete`
Confirm completion of direct upload and create database record.

- **Body** (JSON)
  ```json
  {
    "file_key": "visa-applications/123/files/2024-10-28_143022_a1b2c3d4.pdf",
    "filename": "passport.pdf",
    "content_type": "application/pdf",
    "file_size": 5242880,
    "file_category_id": 1
  }
  ```
- **Response `200`**
  ```json
  {
    "success": true,
    "data": {
      "file": {
        "id": 456,
        "file_name": "passport.pdf",
        "file_size": 5242880,
        "mime_type": "application/pdf",
        "created_at": "2024-10-28T14:30:25+00:00"
      }
    },
    "message": "File upload completed successfully."
  }
  ```
- **Validation Errors `422`**
  - All fields are required
  - `file_key` must match the key from initiate response
  - `file_category_id` must exist in database

### POST `/api/visa-applications/{visa_application}/files/multipart/initiate`
Initiate multipart upload for files 50MB or larger.

- **Body** (JSON)
  ```json
  {
    "file_category_id": 1,
    "file_name": "large-document.pdf",
    "file_size": 104857600,
    "mime_type": "application/pdf",
    "total_parts": 20
  }
  ```
- **Response `200`**
  ```json
  {
    "success": true,
    "data": {
      "upload_id": "abc123xyz789",
      "key": "visa-applications/123/files/2024-10-28_143530_e5f6g7h8.pdf",
      "presigned_urls": [
        {
          "part_number": 1,
          "url": "https://minio.example.com/bucket/..."
        },
        {
          "part_number": 2,
          "url": "https://minio.example.com/bucket/..."
        }
      ],
      "expires_in": 3600
    }
  }
  ```
- **Validation Errors `422`**
  - `file_size` max 524288000 bytes (500MB)
  - `total_parts` max 10000 parts
  - `file_category_id` must exist

**Client Flow:**
1. Request upload initiation with file metadata
2. Upload each part using `PUT` to respective pre-signed URLs
3. Collect ETags from upload responses
4. Call completion endpoint with `upload_id` and parts array

### POST `/api/visa-applications/{visa_application}/files/multipart/complete`
Complete multipart upload by assembling all uploaded parts.

- **Body** (JSON)
  ```json
  {
    "upload_id": "abc123xyz789",
    "parts": [
      { "part_number": 1, "etag": "abc123..." },
      { "part_number": 2, "etag": "def456..." }
    ]
  }
  ```
- **Response `200`**
  ```json
  {
    "success": true,
    "data": {
      "file": {
        "id": 457,
        "file_name": "large-document.pdf",
        "file_size": 104857600,
        "mime_type": "application/pdf",
        "created_at": "2024-10-28T14:40:15+00:00"
      }
    },
    "message": "Multipart upload completed successfully."
  }
  ```
- **Validation Errors `422`**
  - `upload_id` is required
  - `parts` array is required with at least 1 part
  - Each part must have `part_number` (integer) and `etag` (string)

### POST `/api/visa-applications/{visa_application}/files/multipart/abort`
Cancel a multipart upload and clean up partial data.

- **Body** (JSON)
  ```json
  {
    "upload_id": "abc123xyz789"
  }
  ```
- **Response `200`**
  ```json
  {
    "success": true,
    "message": "Multipart upload aborted successfully."
  }
  ```
- **Error `500`** – when upload session not found or S3 operation fails

**When to Use Which Upload Method:**
- **Files < 50MB**: Use Direct Upload (simpler, faster, single request)
- **Files ≥ 50MB**: Use Multipart Upload (parallel chunks, resume capability, progress tracking)

**Security Notes:**
- All endpoints require authentication via Sanctum
- Users can only upload to their own visa applications (Gate authorization)
- Pre-signed URLs expire after 1 hour
- File size limits enforced at validation layer

**Configuration:**
```env
MINIO_ENDPOINT=http://minio:9000
MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_BUCKET=visa-applications
```

See `/docs/direct-to-storage-upload.md` for detailed implementation guide and frontend examples.

---

## Visa Files (Continued)

### DELETE `/api/visa-applications/{visa_application}/files/{visa_applicant_file}`
Delete an uploaded file that belongs to the authenticated applicant and the specified visa application.
- **Response `200`**
  ```json
  { "data": { "deleted": true }, "errors": null }
  ```

---

## Authentication

There are two supported auth modes.

- SPA Cookie Session (first-party)
  1) Initialize CSRF: `GET /sanctum/csrf-cookie`
  2) Login: `POST /login` with JSON `{ "email", "password" }`
     - The server sets `XSRF-TOKEN` and the session cookie. Your HTTP client must send `credentials: include` and the `X-XSRF-TOKEN` header whose value is the URL-decoded `XSRF-TOKEN` cookie.
  3) Auth check: `GET /api/auth/me` returns the authenticated user. This endpoint works with session cookies and tokens.
  4) Logout: `POST /logout`

- Token (Personal Access Tokens)
  1) Create token: `POST /api/auth/token/create` with JSON `{ "email", "password", "device_name" }`
     - Response contains `data.token` and `data.user`.
  2) Use the token: add `Authorization: Bearer <token>` to requests against `/api/*`.
  3) Revoke token: `POST /api/auth/token/revoke` (with the same `Authorization: Bearer <token>` header).
  4) Optional token-only check: `GET /api/auth/me-token` validates only the Bearer token (no session fallback). Intended for tests and third-party tooling; SPA does not use this.

Notes
- CORS: `config/cors.php` enables credentials and whitelists dev origins (`http://localhost:5173`, etc.). Paths include `api/*`, `sanctum/csrf-cookie`, `login`, `logout`.
- Stateful domains: `config/sanctum.php` includes localhost/127.0.0.1 dev ports so SPA cookies authenticate.
- SSR: The frontend uses a server-side API base (`VITE_API_BASE_URL_SERVER`) so loaders can reach the Laravel container during SSR. Requests forward `Cookie`, `Origin`, and `Referer` headers to keep Sanctum “stateful”.

---

## Error Examples

Failed validation always returns the shared error envelope:

```json
{
  "data": null,
  "errors": {
    "message": "Validation failed",
    "details": {
      "file": ["The file must be a file of type: application/pdf, image/png, image/jpeg."]
    }
  }
}
```
