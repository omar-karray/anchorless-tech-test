<?php

use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\DirectFileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->visaApplication = VisaApplication::factory()->create([
        'applicant_id' => $this->user->id,
    ]);
    $this->fileCategory = FileCategory::factory()->create([
        'name' => 'Passport',
        'description' => 'Passport document',
    ]);
});

// ---------------------------------------------------------------
// Authentication & Authorization Tests
// ---------------------------------------------------------------

test('unauthenticated users cannot initiate direct upload', function () {
    $response = $this->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
        'filename' => 'document.pdf',
        'content_type' => 'application/pdf',
        'file_size' => 5242880, // 5MB
    ]);

    $response->assertStatus(401);
});

test('users cannot initiate direct upload for other users visa applications', function () {
    $otherUser = User::factory()->create();
    $otherApplication = VisaApplication::factory()->create([
        'applicant_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $otherApplication), [
            'filename' => 'document.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 5242880,
        ]);

    $response->assertStatus(403);
});

test('unauthenticated users cannot complete direct upload', function () {
    $response = $this->postJson(route('visa-applications.files.direct-upload.complete', $this->visaApplication), [
        'file_key' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
        'filename' => 'document.pdf',
        'content_type' => 'application/pdf',
        'file_size' => 5242880,
    ]);

    $response->assertStatus(401);
});

// ---------------------------------------------------------------
// Validation Tests
// ---------------------------------------------------------------

test('initiate direct upload requires filename', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'content_type' => 'application/pdf',
            'file_size' => 5242880,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.details.filename', fn($value) => !empty($value));
});

test('initiate direct upload requires content_type', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'filename' => 'document.pdf',
            'file_size' => 5242880,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.details.content_type', fn($value) => !empty($value));
});

test('initiate direct upload requires file_size', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'filename' => 'document.pdf',
            'content_type' => 'application/pdf',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.details.file_size', fn($value) => !empty($value));
});

test('initiate direct upload rejects files over 50MB', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'filename' => 'large_file.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 52428801, // Just over 50MB
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.details.file_size', fn($value) => !empty($value));
    $response->assertJsonPath('errors.details.file_size.0', fn($message) => 
        str_contains($message, 'Direct upload is only available for files under 50MB')
    );
});

test('complete direct upload requires file_key', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.complete', $this->visaApplication), [
            'filename' => 'document.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 5242880,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.details.file_key', fn($value) => !empty($value));
});

// ---------------------------------------------------------------
// Happy Path Tests
// ---------------------------------------------------------------

test('authenticated user can initiate direct upload', function () {
    $mock = \Mockery::mock(DirectFileUploadService::class);
    $mock->shouldReceive('generateDirectUploadUrl')
        ->once()
        ->with($this->visaApplication->id, 'document.pdf', 'application/pdf')
        ->andReturn([
            'file_key' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
            'presigned_url' => 'https://minio.test/bucket/visa-applications/1/files/2024-10-28_123456_abc123.pdf?signature=xyz',
            'expiry' => now()->addHour()->toIso8601String(),
        ]);

    $this->app->instance(DirectFileUploadService::class, $mock);

    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'filename' => 'document.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 5242880, // 5MB
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'file_key',
            'presigned_url',
            'expiry',
        ],
        'errors',
    ]);
    $response->assertJson([
        'data' => [
            'file_key' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
        ],
    ]);
});

test('authenticated user can complete direct upload', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.complete', $this->visaApplication), [
            'file_key' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
            'filename' => 'document.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 5242880,
            'file_category_id' => $this->fileCategory->id,
        ]);

    if ($response->status() !== 200) {
        dump($response->json());
    }

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'file' => [
                'id',
                'file_name',
                'file_size',
                'mime_type',
                'created_at',
            ],
        ],
        'errors',
    ]);
    $response->assertJson([
        'data' => [
            'file' => [
                'file_name' => 'document.pdf',
                'file_size' => 5242880,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    // Verify file record was created in database
    $this->assertDatabaseHas('visa_applicant_files', [
        'visa_application_id' => $this->visaApplication->id,
        'original_name' => 'document.pdf',
        'path' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
        'size_bytes' => 5242880,
        'mime_type' => 'application/pdf',
    ]);
});

test('direct upload accepts files exactly at 50MB limit', function () {
    $mock = \Mockery::mock(DirectFileUploadService::class);
    $mock->shouldReceive('generateDirectUploadUrl')
        ->once()
        ->andReturn([
            'file_key' => 'visa-applications/1/files/2024-10-28_123456_abc123.pdf',
            'presigned_url' => 'https://minio.test/bucket/file?signature=xyz',
            'expiry' => now()->addHour()->toIso8601String(),
        ]);

    $this->app->instance(DirectFileUploadService::class, $mock);

    $response = $this->actingAs($this->user)
        ->postJson(route('visa-applications.files.direct-upload.initiate', $this->visaApplication), [
            'filename' => 'large_document.pdf',
            'content_type' => 'application/pdf',
            'file_size' => 52428800, // Exactly 50MB
        ]);

    $response->assertStatus(200);
});
