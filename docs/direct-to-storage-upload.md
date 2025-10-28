# Direct-to-Storage File Upload API

This branch (`multipart-file-upload`) demonstrates **direct-to-storage** file uploads, where files are uploaded directly from the browser to MinIO S3-compatible storage using pre-signed URLs. This approach eliminates the need to proxy large files through the Laravel backend.

## Architecture Overview

```
┌─────────────┐                    ┌──────────────┐                    ┌────────────┐
│             │  1. Request URL    │              │                    │            │
│   Browser   ├───────────────────►│   Laravel    │                    │   MinIO    │
│   (React)   │                    │   Backend    │                    │    S3      │
│             │◄───────────────────┤              │                    │            │
│             │  2. Pre-signed URL │              │                    │            │
│             │                    └──────────────┘                    │            │
│             │                                                         │            │
│             │  3. Upload file directly                               │            │
│             ├────────────────────────────────────────────────────────►│            │
│             │                                                         │            │
│             │  4. Confirm completion   ┌──────────────┐              │            │
│             ├─────────────────────────►│   Laravel    │              │            │
│             │                          │   Backend    │              │            │
└─────────────┘                          └──────────────┘              └────────────┘
```

## Upload Strategy: File Size-Based Routing

### 1. **Direct Upload** (Files < 50MB)
- **Use case**: Small to medium-sized files (documents, images, PDFs)
- **Method**: Single PUT request with entire file
- **Endpoints**: 
  - `POST /api/visa-applications/{id}/files/direct-upload/initiate`
  - `POST /api/visa-applications/{id}/files/direct-upload/complete`
- **Benefits**: Simple, fast, minimal overhead

### 2. **Multipart Upload** (Files ≥ 50MB)
- **Use case**: Large files (videos, high-res scans, multi-page documents)
- **Method**: File split into 5MB chunks, uploaded in parallel
- **Endpoints**:
  - `POST /api/visa-applications/{id}/files/multipart/initiate`
  - `POST /api/visa-applications/{id}/files/multipart/complete`
  - `POST /api/visa-applications/{id}/files/multipart/abort`
- **Benefits**: Resume capability, progress tracking, parallel uploads, better error recovery

---

## How It Works: Complete Upload Flow

### Understanding the Three-Step Process

The direct-to-storage upload pattern separates the **control plane** (Laravel) from the **data plane** (MinIO). The frontend orchestrates between both:

```
┌─────────────────────────────────────────────────────────────────┐
│                     STEP 1: INITIATE                            │
├─────────────────────────────────────────────────────────────────┤
│ Frontend → Laravel:                                             │
│   POST /api/visa-applications/{id}/files/direct-upload/initiate │
│   { filename, content_type, file_size }                         │
│                                                                  │
│ Laravel → MinIO:                                                │
│   "Generate pre-signed URL for this file path"                  │
│                                                                  │
│ Laravel → Frontend:                                             │
│   { file_key, presigned_url, expiry }                           │
│   ⚠️  NO database record yet - just prepared the upload path    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                  STEP 2: UPLOAD TO MINIO                        │
├─────────────────────────────────────────────────────────────────┤
│ Frontend → MinIO (DIRECT - no Laravel proxy):                  │
│   PUT presigned_url                                             │
│   Body: <file bytes>                                            │
│   Headers: Content-Type                                         │
│                                                                  │
│ MinIO:                                                          │
│   ✅ Stores file at file_key path                               │
│   ✅ Returns ETag in response headers                           │
│   ⚠️  Laravel doesn't know yet - file exists but not in DB      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│              STEP 3: COMPLETE (Backend Notification)            │
├─────────────────────────────────────────────────────────────────┤
│ Frontend → Laravel:                                             │
│   POST /api/visa-applications/{id}/files/direct-upload/complete │
│   { file_key, filename, content_type, file_size, category_id } │
│   ⭐ THIS IS HOW BACKEND KNOWS FILE IS UPLOADED!                │
│                                                                  │
│ Laravel:                                                        │
│   1. ✅ Validates user owns this visa application               │
│   2. ✅ Trusts that file exists in MinIO at file_key            │
│   3. ✅ Creates VisaApplicantFile record in database            │
│   4. ✅ Returns success response with file metadata             │
│                                                                  │
│ Frontend:                                                       │
│   ✅ Shows success message immediately                          │
│   ✅ Revalidates to fetch updated file list                     │
└─────────────────────────────────────────────────────────────────┘
```

### Multipart Upload Flow (Files ≥ 50MB)

For large files, the process is similar but with multiple parts:

```
┌─────────────────────────────────────────────────────────────────┐
│                STEP 1: INITIATE MULTIPART                       │
├─────────────────────────────────────────────────────────────────┤
│ Frontend → Laravel:                                             │
│   POST /api/visa-applications/{id}/files/multipart/initiate     │
│   { file_name, file_size, mime_type, total_parts, category }   │
│                                                                  │
│ Laravel → MinIO:                                                │
│   CreateMultipartUpload API call                                │
│   Receives upload_id                                            │
│                                                                  │
│ Laravel:                                                        │
│   Generates N pre-signed URLs (one per part)                    │
│   Stores session metadata in memory                             │
│                                                                  │
│ Laravel → Frontend:                                             │
│   { upload_id, key, presigned_urls[], expires_in }             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│              STEP 2: UPLOAD PARTS TO MINIO                      │
├─────────────────────────────────────────────────────────────────┤
│ Frontend:                                                       │
│   1. Split file into 5MB chunks                                 │
│   2. Upload chunks in parallel (concurrency: 3)                 │
│                                                                  │
│ For each part:                                                  │
│   Frontend → MinIO:                                             │
│     PUT presigned_urls[i].url                                   │
│     Body: <chunk bytes>                                         │
│                                                                  │
│   MinIO → Frontend:                                             │
│     ETag header (required for completion)                       │
│                                                                  │
│ Frontend:                                                       │
│   ✅ Tracks progress across all parts                           │
│   ✅ Collects ETags from each part upload                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│            STEP 3: COMPLETE MULTIPART UPLOAD                    │
├─────────────────────────────────────────────────────────────────┤
│ Frontend → Laravel:                                             │
│   POST /api/visa-applications/{id}/files/multipart/complete     │
│   { upload_id, parts: [{ part_number, etag }, ...] }           │
│                                                                  │
│ Laravel → MinIO:                                                │
│   CompleteMultipartUpload API call                              │
│   MinIO assembles all parts into final file                     │
│                                                                  │
│ Laravel:                                                        │
│   1. ✅ Creates VisaApplicantFile record in database            │
│   2. ✅ Cleans up session metadata                              │
│   3. ✅ Returns file metadata                                   │
│                                                                  │
│ Frontend:                                                       │
│   ✅ Shows success message                                      │
│   ✅ Revalidates to display new file                            │
└─────────────────────────────────────────────────────────────────┘
```

### How Backend Knows File is Uploaded

**Critical Understanding:** The backend doesn't automatically know when a file is uploaded to MinIO. The frontend **must explicitly notify** the backend by calling the `/complete` endpoint.

#### Why This Design?

1. **Separation of Concerns**
   - **Control Plane (Laravel):** Authentication, authorization, metadata management
   - **Data Plane (MinIO):** Actual file storage
   - Frontend orchestrates between both

2. **Performance & Scalability**
   - Laravel doesn't proxy file bytes (saves CPU, memory, bandwidth)
   - MinIO handles file storage directly (what it's built for)
   - Multiple concurrent uploads don't overload Laravel

3. **Trust Model**
   - Frontend can't fake successful MinIO upload (CORS + pre-signed URLs)
   - Pre-signed URLs have short expiry (1 hour)
   - Only authenticated users can complete uploads
   - Users have no incentive to lie (uploading their own documents)

#### Security Considerations

**Current Implementation:**
```php
public function complete(...) {
    // Backend trusts frontend's claim that upload succeeded
    $file = VisaApplicantFile::create([
        'path' => $validated['file_key'],
        'size_bytes' => $validated['file_size'],
        // ...
    ]);
}
```

**If you need extra validation:**
```php
public function complete(...) {
    $validated = $request->validated();
    
    // Optional: Verify file exists in MinIO before creating DB record
    if (!Storage::disk('minio')->exists($validated['file_key'])) {
        return response()->json([
            'success' => false,
            'message' => 'File not found in storage',
        ], 400);
    }
    
    // Optional: Verify file size matches
    $actualSize = Storage::disk('minio')->size($validated['file_key']);
    if ($actualSize !== $validated['file_size']) {
        return response()->json([
            'success' => false,
            'message' => 'File size mismatch',
        ], 400);
    }
    
    // Create database record...
}
```

**Trade-offs:**
- ✅ **Without verification:** Faster, minimal S3 API calls, trusts client
- ✅ **With verification:** More secure, catches edge cases, but adds latency

For most use cases, the trust model is sufficient because:
- Pre-signed URLs can only be used once
- URLs expire after 1 hour
- User can't access file later if it doesn't exist
- Authentication/authorization is enforced

### What If Frontend Crashes Between Steps 2 and 3?

**Scenario:** File uploads to MinIO successfully, but frontend crashes before calling `/complete`

**Result:**
- ✅ File exists in MinIO at the generated file_key path
- ❌ No database record created
- ❌ User doesn't see file in their application

**Solutions:**

1. **Manual Cleanup (Recommended)**
   - Implement scheduled job to find "orphaned" files
   - Compare MinIO files with database records
   - Delete files older than 24 hours without DB record

2. **Client-Side Resume**
   - Store upload state in localStorage
   - On page reload, check for incomplete uploads
   - Retry `/complete` endpoint

3. **MinIO Lifecycle Rules**
   - Configure bucket lifecycle rules
   - Auto-delete incomplete multipart uploads after N hours
   - Auto-delete files in temp directory after N hours

**Example Cleanup Job:**
```php
// Scheduled job to clean up orphaned files
public function handle() {
    $filesInMinIO = Storage::disk('minio')->allFiles('visa-applications');
    
    foreach ($filesInMinIO as $filePath) {
        // Check if file has DB record
        $exists = VisaApplicantFile::where('path', $filePath)->exists();
        
        if (!$exists) {
            $fileAge = Storage::disk('minio')->lastModified($filePath);
            
            // Delete if older than 24 hours
            if (now()->timestamp - $fileAge > 86400) {
                Storage::disk('minio')->delete($filePath);
                Log::info("Cleaned up orphaned file: {$filePath}");
            }
        }
    }
}
```

### Frontend Implementation: How It All Connects

**File:** `app/lib/direct-upload.ts`

```typescript
export async function uploadFileDirect(file, visaApplicationId, categoryId, onProgress) {
  if (file.size < 50MB) {
    return await uploadDirect(file, visaApplicationId, categoryId, onProgress);
  } else {
    return await uploadMultipart(file, visaApplicationId, categoryId, onProgress);
  }
}

async function uploadDirect(file, visaApplicationId, categoryId, onProgress) {
  // STEP 1: Get pre-signed URL from Laravel
  const initResponse = await apiFetch('/files/direct-upload/initiate', {
    method: 'POST',
    body: JSON.stringify({ filename: file.name, content_type: file.type, file_size: file.size })
  });
  
  const { file_key, presigned_url } = initResponse.data;
  
  // STEP 2: Upload directly to MinIO (bypassing Laravel)
  await new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', (e) => {
      if (onProgress) {
        onProgress({ loaded: e.loaded, total: e.total, percentage: (e.loaded/e.total)*100 });
      }
    });
    
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve();
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`));
      }
    });
    
    xhr.open('PUT', presigned_url);
    xhr.setRequestHeader('Content-Type', file.type);
    xhr.send(file);  // ← DIRECT TO MINIO
  });
  
  // STEP 3: Notify Laravel that upload completed
  const completeResponse = await apiFetch('/files/direct-upload/complete', {
    method: 'POST',
    body: JSON.stringify({
      file_key,
      filename: file.name,
      content_type: file.type,
      file_size: file.size,
      file_category_id: categoryId
    })
  });
  
  return completeResponse;
}
```

**File:** `app/routes/dashboard.applications.$id.edit.tsx`

```typescript
const handleFileSelect = async (categoryId, file) => {
  setUploadingCategories(prev => new Set(prev).add(categoryId));
  setUploadProgress(prev => new Map(prev).set(categoryId, 0));
  
  try {
    // This handles everything: initiate → upload → complete
    await uploadFileDirect(
      file,
      application.id,
      categoryId,
      (progress) => {
        setUploadProgress(prev => new Map(prev).set(categoryId, progress.percentage));
      }
    );
    
    // Show success immediately (no WebSocket needed!)
    showSuccess(`File "${file.name}" uploaded successfully!`, categoryId);
    
    // Revalidate to fetch updated file list
    revalidator.revalidate();
    
  } catch (error) {
    setUploadError("Failed to upload file. Please try again.");
  } finally {
    setUploadingCategories(prev => {
      const next = new Set(prev);
      next.delete(categoryId);
      return next;
    });
  }
};
```

### Key Differences from Queue-Based Upload

| Aspect | Queue-Based (Main Branch) | Direct Upload (This Branch) |
|--------|---------------------------|----------------------------|
| **Data Flow** | Browser → Laravel → Queue → Worker → MinIO | Browser → MinIO (direct) |
| **Backend Knowledge** | WebSocket event after queue processes | Frontend calls `/complete` endpoint |
| **Progress Tracking** | Via WebSocket updates | Via XHR progress events (real-time) |
| **Feedback Timing** | Delayed (queue processing time) | Immediate (no queue) |
| **Backend Load** | High (proxies all file bytes) | Minimal (only auth + metadata) |
| **Scalability** | Limited by queue workers | Unlimited (client-side) |
| **Dependencies** | Requires Horizon + Reverb | Only MinIO + pre-signed URLs |
| **Use Case** | Good for post-processing (thumbnails, OCR) | Perfect for simple file storage |

---

## Direct Upload API (< 50MB)

### 1. Initiate Direct Upload

**Request:**
```http
POST /api/visa-applications/{id}/files/direct-upload/initiate
Content-Type: application/json
Authorization: Bearer {token}

{
  "filename": "passport.pdf",
  "content_type": "application/pdf",
  "file_size": 5242880
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "file_key": "visa-applications/123/files/2024-10-28_143022_a1b2c3d4.pdf",
    "presigned_url": "https://minio.example.com/bucket/visa-applications/123/files/2024-10-28_143022_a1b2c3d4.pdf?X-Amz-Algorithm=...",
    "expiry": "2024-10-28T15:30:22+00:00"
  },
  "message": "Pre-signed URL generated successfully. Upload your file directly to the provided URL."
}
```

### 2. Upload File to MinIO

```javascript
// Frontend code (React Router)
const response = await fetch(presignedUrl, {
  method: 'PUT',
  headers: {
    'Content-Type': contentType,
  },
  body: file,
});

if (!response.ok) {
  throw new Error('Upload failed');
}
```

### 3. Complete Upload

**Request:**
```http
POST /api/visa-applications/{id}/files/direct-upload/complete
Content-Type: application/json
Authorization: Bearer {token}

{
  "file_key": "visa-applications/123/files/2024-10-28_143022_a1b2c3d4.pdf",
  "filename": "passport.pdf",
  "content_type": "application/pdf",
  "file_size": 5242880,
  "file_category_id": 1
}
```

**Response:**
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

---

## Multipart Upload API (≥ 50MB)

### 1. Initiate Multipart Upload

**Request:**
```http
POST /api/visa-applications/{id}/files/multipart/initiate
Content-Type: application/json
Authorization: Bearer {token}

{
  "file_category_id": 1,
  "file_name": "large-document.pdf",
  "file_size": 104857600,
  "mime_type": "application/pdf",
  "total_parts": 20
}
```

**Response:**
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
      // ... up to 20 parts
    ],
    "expires_in": 3600
  }
}
```

### 2. Upload Parts to MinIO

```javascript
// Frontend code (React Router)
const chunkSize = 5 * 1024 * 1024; // 5MB chunks
const parts = [];

for (let i = 0; i < presignedUrls.length; i++) {
  const start = i * chunkSize;
  const end = Math.min(start + chunkSize, file.size);
  const chunk = file.slice(start, end);
  
  const response = await fetch(presignedUrls[i].url, {
    method: 'PUT',
    body: chunk,
  });
  
  const etag = response.headers.get('ETag').replace(/"/g, '');
  parts.push({
    part_number: i + 1,
    etag: etag,
  });
}
```

### 3. Complete Multipart Upload

**Request:**
```http
POST /api/visa-applications/{id}/files/multipart/complete
Content-Type: application/json
Authorization: Bearer {token}

{
  "upload_id": "abc123xyz789",
  "parts": [
    { "part_number": 1, "etag": "abc123..." },
    { "part_number": 2, "etag": "def456..." }
    // ... all parts
  ]
}
```

**Response:**
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

### 4. Abort Multipart Upload (Optional)

**Request:**
```http
POST /api/visa-applications/{id}/files/multipart/abort
Content-Type: application/json
Authorization: Bearer {token}

{
  "upload_id": "abc123xyz789"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Multipart upload aborted successfully."
}
```

---

## Frontend Implementation Guide

### Decision Logic

```javascript
const FILE_SIZE_THRESHOLD = 50 * 1024 * 1024; // 50MB

async function uploadFile(file, visaApplicationId, fileCategoryId) {
  if (file.size < FILE_SIZE_THRESHOLD) {
    return await uploadDirect(file, visaApplicationId, fileCategoryId);
  } else {
    return await uploadMultipart(file, visaApplicationId, fileCategoryId);
  }
}
```

### Direct Upload Implementation

```javascript
async function uploadDirect(file, visaApplicationId, fileCategoryId) {
  // 1. Request pre-signed URL
  const initResponse = await fetch(
    `/api/visa-applications/${visaApplicationId}/files/direct-upload/initiate`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({
        filename: file.name,
        content_type: file.type,
        file_size: file.size,
      }),
    }
  );
  
  const { data } = await initResponse.json();
  const { file_key, presigned_url } = data;
  
  // 2. Upload to MinIO
  await fetch(presigned_url, {
    method: 'PUT',
    headers: { 'Content-Type': file.type },
    body: file,
  });
  
  // 3. Complete upload
  const completeResponse = await fetch(
    `/api/visa-applications/${visaApplicationId}/files/direct-upload/complete`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({
        file_key,
        filename: file.name,
        content_type: file.type,
        file_size: file.size,
        file_category_id: fileCategoryId,
      }),
    }
  );
  
  return await completeResponse.json();
}
```

### Multipart Upload Implementation

```javascript
async function uploadMultipart(file, visaApplicationId, fileCategoryId) {
  const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
  const totalParts = Math.ceil(file.size / CHUNK_SIZE);
  
  // 1. Initiate multipart upload
  const initResponse = await fetch(
    `/api/visa-applications/${visaApplicationId}/files/multipart/initiate`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({
        file_category_id: fileCategoryId,
        file_name: file.name,
        file_size: file.size,
        mime_type: file.type,
        total_parts: totalParts,
      }),
    }
  );
  
  const { data } = await initResponse.json();
  const { upload_id, presigned_urls } = data;
  
  // 2. Upload parts in parallel
  const parts = await Promise.all(
    presigned_urls.map(async ({ part_number, url }) => {
      const start = (part_number - 1) * CHUNK_SIZE;
      const end = Math.min(start + CHUNK_SIZE, file.size);
      const chunk = file.slice(start, end);
      
      const response = await fetch(url, {
        method: 'PUT',
        body: chunk,
      });
      
      const etag = response.headers.get('ETag').replace(/"/g, '');
      
      return { part_number, etag };
    })
  );
  
  // 3. Complete multipart upload
  const completeResponse = await fetch(
    `/api/visa-applications/${visaApplicationId}/files/multipart/complete`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({
        upload_id,
        parts,
      }),
    }
  );
  
  return await completeResponse.json();
}
```

---

## Error Handling

### Common Errors

#### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

#### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

#### 422 Validation Error
```json
{
  "data": null,
  "errors": {
    "message": "Validation failed",
    "details": {
      "file_size": [
        "Direct upload is only available for files under 50MB. Please use multipart upload for larger files."
      ]
    }
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "message": "Failed to generate upload URL",
  "error": "Connection to storage service failed"
}
```

---

## Configuration

### Environment Variables

```env
# MinIO S3 Configuration
MINIO_ENDPOINT=http://minio:9000
MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_REGION=us-east-1
MINIO_BUCKET=visa-applications
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

### Laravel Configuration

File: `config/filesystems.php`

```php
'minio' => [
    'driver' => 's3',
    'key' => env('MINIO_KEY'),
    'secret' => env('MINIO_SECRET'),
    'region' => env('MINIO_REGION', 'us-east-1'),
    'bucket' => env('MINIO_BUCKET'),
    'endpoint' => env('MINIO_ENDPOINT'),
    'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE_ENDPOINT', true),
],
```

---

## Testing

### Run Direct Upload Tests

```bash
docker compose exec laravel.test php artisan test tests/Feature/DirectUpload/DirectUploadTest.php
```

### Run Multipart Upload Tests

```bash
docker compose exec laravel.test php artisan test tests/Feature/MultipartUpload/MultipartUploadTest.php
```

### Run All Tests

```bash
docker compose exec laravel.test php artisan test
```

---

## Security Considerations

1. **Pre-signed URL Expiry**: URLs expire after 1 hour
2. **Authorization**: All endpoints require authentication via Sanctum
3. **Gate Policies**: Users can only upload to their own visa applications
4. **File Size Limits**: 
   - Direct upload: Max 50MB
   - Multipart upload: Max 500MB (configurable)
5. **Content Type Validation**: Ensure file types match expected MIME types
6. **Rate Limiting**: Consider implementing rate limits on API endpoints

---

## Advantages vs Queue-Based Uploads

| Feature | Direct-to-Storage | Queue-Based (Other Branch) |
|---------|-------------------|----------------------------|
| Upload Speed | ⚡ Fast (direct) | 🐌 Slower (proxied) |
| Server Load | ✅ Minimal | ❌ High (memory/CPU) |
| Scalability | ✅ Excellent | ⚠️ Limited by workers |
| Progress Tracking | ✅ Real-time (client) | ⚠️ Via WebSocket |
| Resume Capability | ✅ Yes (multipart) | ❌ No |
| Parallel Uploads | ✅ Yes (multipart) | ❌ Sequential |
| Network Efficiency | ✅ Single hop | ❌ Double hop |

---

## Production Considerations

1. **CORS Configuration**: Ensure MinIO allows cross-origin requests from your frontend domain
2. **Pre-signed URL Security**: Consider shorter expiry times in production
3. **Monitoring**: Track failed uploads, completion rates, and average upload times
4. **Cleanup**: Implement a job to clean up aborted multipart uploads after 24 hours
5. **CDN Integration**: Consider CloudFront or similar for global distribution
6. **File Validation**: Validate file integrity after upload (checksums, virus scanning)

---

## Troubleshooting

### "Pre-signed URL expired"
- URLs are valid for 1 hour. Request a new URL if expired.

### "CORS policy error"
- Check MinIO CORS configuration: `mc admin config set minio cors`

### "Upload fails with 403"
- Verify MinIO credentials in `.env`
- Check bucket permissions

### "Multipart upload stuck"
- Use the abort endpoint to clean up
- Check network connectivity

---

## Next Steps for Frontend

1. ✅ Implement file size detection
2. ✅ Route to appropriate upload method
3. ⏳ Add progress bars for uploads
4. ⏳ Implement retry logic for failed chunks
5. ⏳ Add pause/resume for multipart uploads
6. ⏳ Show real-time upload status

---

## References

- [AWS S3 Pre-signed URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/PresignedUrlUploadObject.html)
- [AWS S3 Multipart Upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
- [MinIO Documentation](https://min.io/docs/minio/linux/index.html)
