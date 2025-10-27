# Backend Architecture Overview

This page explains the key moving parts of the Laravel backend, including how we structure API responses, validation, file uploads, queuing, and real-time notifications.

## API Conventions

- **Envelope:** Every JSON response is wrapped as `{ "data": ..., "errors": null }`. Errors set `data` to `null` and populate an `errors` object.
- **Form Requests:** Input validation lives in dedicated `FormRequest` classes (e.g. `StoreVisaApplicantFileRequest`). Controllers receive already-validated data.
- **Resources:** Outbound payloads use `JsonResource` classes (e.g. `VisaApplicantFileResource`) to keep presentation logic consistent.
- **Policies & Gates:** Authorization is handled via Laravel policies (`App\Policies`) and enforced inside controllers (`$this->authorize(...)`).

## File Upload Pipeline

1. **Controller (`VisaApplicantFilesController@store`):**
   - Validates the upload via `StoreVisaApplicantFileRequest`.
   - Uses the `FileUploadService` to persist the raw file temporarily and enqueue processing.
   - Returns `202 Accepted` with a “queued for processing” message.

2. **Service (`App\Services\FileUploadService`):**
   - Stores the uploaded file under `storage/app/private/tmp/visa-applications/{id}`.
   - Dispatches the `StoreVisaApplicantFile` job, passing metadata (user, category, paths, sizes).

3. **Job (`App\Jobs\StoreVisaApplicantFile`):**
   - Runs on the `file-uploads` queue (processed by Horizon).
   - Moves the file to its permanent location (`storage/app/private/visa-applications/{id}/files`).
   - Creates the `VisaApplicantFile` database record.
   - Broadcasts outcome over Reverb:
     - `VisaApplicantFileStored` on success (`status: stored`, payload includes the resource).
     - `VisaApplicantFileFailed` on failure (`status: failed`, includes reason codes such as `temporary_file_missing`).

4. **Broadcast Channels:**
   - Events publish to `private-visa-applications.{visaApplicationId}`.
   - `routes/channels.php` authorizes access by checking the `view` policy on `VisaApplication`.

## Queues & Horizon

- Horizon supervises dedicated workers with separate queues (`default`, `emails`, `file-uploads`) configured in `config/horizon.php`.
- The Horizon container runs `php artisan horizon` continuously (see `docker-compose.yaml`).
- Additional queueable tasks (email notifications, etc.) should specify the appropriate queue name inside the job constructor.

## Real-Time Broadcasting with Reverb

- Reverb runs via `php artisan reverb:start` in its own container and listens on port 8080.
- Frontend clients (React app or CLI tools) connect using the credentials defined in `.env` (`REVERB_APP_KEY`, `REVERB_HOST`, etc.).
- Broadcasting configuration lives in `config/broadcasting.php`. We default to the `reverb` driver.

## Events & Notifications

- `VisaApplicationSubmitted` / `SendVisaApplicationSubmittedNotification` handle email notifications when applications move to `submitted`.
- The queue-driven file upload broadcasts (detailed above) give real-time visibility into file processing.
- Future events can leverage the same structure: dispatch a domain event when state changes, then broadcast if the UI needs to react immediately.

## Dependency Injection & Services

- Services (like `FileUploadService`) are bound via the service container in `AppServiceProvider@register`.
- Constructor injection is used in controllers (e.g. `VisaApplicantFilesController` receives `FileUploadServiceContract`).
- Prefer service contracts and dependency injection over resolving classes directly (`app()->make(...)`) to aid testing and future refactoring.

## Testing Strategy

- **Feature Tests:** Live under `tests/Feature/...`, exercising API endpoints, auth flows, and integration behaviour with the database.
- **Unit Tests:** Cover isolated components like jobs (`StoreVisaApplicantFileTest.php`), using storage and event fakes to assert behaviour.
- Pest is used for its expressive syntax (`./vendor/bin/pest`).

## Dockerised Environment

- Docker Compose defines services for the Laravel runtime, Redis, PostgreSQL, MinIO, Horizon, Reverb, Mailpit, and the optional React frontend.
- Make targets wrap Compose commands to provide repeatable environments (`make app-boot`, `make app-reboot`, etc.).
- Composer dependencies are installed inside the Laravel container during `app:configure`.

This architecture is intentionally modular: controllers stay slim, services encapsulate orchestration, jobs handle background work, and events broadcast state changes. Feel free to extend the service layer for additional domains (e.g. notifications, reporting) following the same patterns.
