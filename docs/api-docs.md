# API Documentation

This section describes the available API endpoints for the Anchorless VISA Dossier Upload feature, including authentication and visa application management.

---

## Authentication Endpoints

### POST `/api/auth/login`
Authenticate a user and receive an access token.
- **Body:**
  - `email`: string
  - `password`: string
- **Response:**
  - Success: `{ "data": { "token": "..." }, "errors": null }`
  - Error: `{ "data": null, "errors": { ... } }`

---

## Visa Application Endpoints

### GET `/api/visa-applications`
List all visa applications for the authenticated user.
- **Response:**
  - `{ "data": [ ...visa applications... ], "errors": null }`

### POST `/api/visa-applications`
Create a new visa application.
- **Body:**
  - `country`: string (ISO 3166-1 alpha-2)
  - `status`: string (draft, submitted, approved, rejected)
- **Response:**
  - Success: `{ "data": { ...visa application... }, "errors": null }`

### GET `/api/visa-applications/{id}`
Get details of a specific visa application.
- **Response:**
  - `{ "data": { ...visa application... }, "errors": null }`

### PUT `/api/visa-applications/{id}`
Update a visa application.
- **Body:**
  - `country`: string
  - `status`: string
- **Response:**
  - Success: `{ "data": { ...updated visa application... }, "errors": null }`

### DELETE `/api/visa-applications/{id}`
Delete a visa application.
- **Response:**
  - Success: `{ "data": { "deleted": true }, "errors": null }`

---

## Visa Application File Endpoints

### POST `/api/visa-applications/{id}/files`
Upload a file to a visa application.
- **Body:** Multipart form data
  - `file`: PDF, PNG, or JPG (max 4MB)
  - `file_category_id`: integer
- **Response:**
  - Success: `{ "data": { ...file metadata... }, "errors": null }`

### GET `/api/visa-applications/{id}/files`
List all files for a visa application.
- **Response:**
  - `{ "data": [ ...files... ], "errors": null }`

### GET `/api/visa-applications/{id}/files/{file_id}`
Get details of a specific file.
- **Response:**
  - `{ "data": { ...file metadata... }, "errors": null }`

### PUT `/api/visa-applications/{id}/files/{file_id}`
Update file metadata (e.g., category).
- **Body:**
  - `file_category_id`: integer
- **Response:**
  - Success: `{ "data": { ...updated file... }, "errors": null }`

### DELETE `/api/visa-applications/{id}/files/{file_id}`
Delete a file from a visa application.
- **Response:**
  - Success: `{ "data": { "deleted": true }, "errors": null }`

---

## Response Format
All responses use a JSON envelope:
```json
{ "data": { ... }, "errors": null }
```
Or on error:
```json
{ "data": null, "errors": { "message": "Validation failed", "details": { ... } } }
```

---

## Example Auth Request
```json
POST /api/auth/login
{
  "email": "test@me.io",
  "password": "password"
}
```

## Example Visa Application
```json
{
  "id": 1,
  "country": "FR",
  "status": "draft",
  "created_at": "2025-10-24T12:00:00Z",
  "updated_at": "2025-10-24T12:00:00Z"
}
```

## Example File Metadata
```json
{
  "id": 1,
  "visa_application_id": 1,
  "file_category_id": 2,
  "original_name": "passport.pdf",
  "stored_name": "abc123.pdf",
  "mime_type": "application/pdf",
  "size_bytes": 102400,
  "path": "files/abc123.pdf",
  "disk": "local",
  "created_at": "2025-10-24T12:00:00Z",
  "updated_at": "2025-10-24T12:00:00Z"
}
```

## Error Example
```json
{
  "data": null,
  "errors": {
    "message": "Validation failed",
    "details": {
      "file": ["The file must be a PDF, PNG, or JPG."],
      "file_category_id": ["The file category field is required."]
    }
  }
}
```
