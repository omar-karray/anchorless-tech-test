<?php

use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\DirectFileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Seed file categories
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FileCategoriesTableSeeder']);

    // Create users
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create visa applications
    $this->visaApplication = VisaApplication::factory()->create([
        'applicant_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $this->otherUserApplication = VisaApplication::factory()->create([
        'applicant_id' => $this->otherUser->id,
        'status' => 'draft',
    ]);

    $this->fileCategory = FileCategory::first();
});

afterEach(function (): void {
    Mockery::close();
});

// ---------------------------------------------------------------
// Initiate Multipart Upload Tests
// ---------------------------------------------------------------

it('prevents unauthenticated users from initiating multipart upload', function (): void {
    $response = postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
        'file_category_id' => $this->fileCategory->id,
        'file_name' => 'test.pdf',
        'file_size' => 1024000,
        'mime_type' => 'application/pdf',
        'total_parts' => 5,
    ]);

    $response->assertUnauthorized();
});

it('prevents users from initiating upload for another users application', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->otherUserApplication), [
            'file_category_id' => $this->fileCategory->id,
            'file_name' => 'test.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'total_parts' => 5,
        ]);

    $response->assertForbidden();
});

it('validates required fields for initiate', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), []);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.file_category_id', fn($value) => !empty($value))
        ->assertJsonPath('errors.details.file_name', fn($value) => !empty($value))
        ->assertJsonPath('errors.details.file_size', fn($value) => !empty($value))
        ->assertJsonPath('errors.details.mime_type', fn($value) => !empty($value))
        ->assertJsonPath('errors.details.total_parts', fn($value) => !empty($value));
});

it('validates file size limits', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
            'file_category_id' => $this->fileCategory->id,
            'file_name' => 'test.pdf',
            'file_size' => 524288001, // Over 500MB limit
            'mime_type' => 'application/pdf',
            'total_parts' => 5,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.file_size', fn($value) => !empty($value));
});

it('validates total parts limit', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
            'file_category_id' => $this->fileCategory->id,
            'file_name' => 'test.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'total_parts' => 10001, // Over 10000 limit
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.total_parts', fn($value) => !empty($value));
});

it('validates file category exists', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
            'file_category_id' => 99999, // Non-existent
            'file_name' => 'test.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'total_parts' => 5,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.file_category_id', fn($value) => !empty($value));
});

it('initiates multipart upload successfully', function (): void {
    // Mock the DirectFileUploadService
    $mockService = Mockery::mock(DirectFileUploadService::class);
    $mockService->shouldReceive('initiateMultipartUpload')
        ->once()
        ->andReturn([
            'upload_id' => 'test-upload-id-123',
            'key' => 'visa-applications/1/files/test-uuid.pdf',
            'presigned_urls' => [
                ['part_number' => 1, 'url' => 'https://minio:9000/bucket/key?part=1'],
                ['part_number' => 2, 'url' => 'https://minio:9000/bucket/key?part=2'],
            ],
            'expires_in' => 3600,
        ]);

    $this->app->instance(DirectFileUploadService::class, $mockService);

    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
            'file_category_id' => $this->fileCategory->id,
            'file_name' => 'test.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'total_parts' => 2,
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'upload_id',
                'key',
                'presigned_urls' => [
                    '*' => ['part_number', 'url'],
                ],
                'expires_in',
            ],
        ])
        ->assertJson([
            'data' => [
                'upload_id' => 'test-upload-id-123',
            ],
        ]);
});

// ---------------------------------------------------------------
// Complete Multipart Upload Tests
// ---------------------------------------------------------------

it('prevents unauthenticated users from completing multipart upload', function (): void {
    $response = postJson(route('visa-applications.files.multipart.complete', $this->visaApplication), [
        'upload_id' => 'test-upload-id',
        'parts' => [
            ['part_number' => 1, 'etag' => '"etag1"'],
        ],
    ]);

    $response->assertUnauthorized();
});

it('prevents users from completing upload for another users application', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.complete', $this->otherUserApplication), [
            'upload_id' => 'test-upload-id',
            'parts' => [
                ['part_number' => 1, 'etag' => '"etag1"'],
            ],
        ]);

    $response->assertForbidden();
});

it('validates required fields for complete', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.complete', $this->visaApplication), []);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.upload_id', fn($value) => !empty($value))
        ->assertJsonPath('errors.details.parts', fn($value) => !empty($value));
});

it('validates parts array structure', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.complete', $this->visaApplication), [
            'upload_id' => 'test-upload-id',
            'parts' => [
                ['part_number' => 1], // Missing etag
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Validation failed'])
        ->assertJsonPath('errors.details', fn($details) => isset($details['parts.0.etag']));
});

it('requires at least one part', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.complete', $this->visaApplication), [
            'upload_id' => 'test-upload-id',
            'parts' => [], // Empty array
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.parts', fn($value) => !empty($value));
});

it('completes multipart upload successfully', function (): void {
    // Create a real file object with mock data
    $file = \App\Models\VisaApplicantFile::factory()->make([
        'id' => 1,
        'visa_application_id' => $this->visaApplication->id,
        'file_category_id' => $this->fileCategory->id,
        'file_name' => 'test.pdf',
        'file_path' => 'visa-applications/1/files/test-uuid.pdf',
        'file_size' => 1024000,
        'mime_type' => 'application/pdf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $file->id = 1; // Set ID explicitly since make() doesn't

    // Mock the DirectFileUploadService
    $mockService = Mockery::mock(DirectFileUploadService::class);
    $mockService->shouldReceive('completeMultipartUpload')
        ->once()
        ->andReturn($file);

    $this->app->instance(DirectFileUploadService::class, $mockService);

    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.complete', $this->visaApplication), [
            'upload_id' => 'test-upload-id',
            'parts' => [
                ['part_number' => 1, 'etag' => '"etag1"'],
                ['part_number' => 2, 'etag' => '"etag2"'],
            ],
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'file_name',
                'file_category_id',
                'uploaded_at',
            ],
        ])
        ->assertJson([
            'data' => [
                'file_name' => 'test.pdf',
                'file_category_id' => $this->fileCategory->id,
            ],
        ]);
});

// ---------------------------------------------------------------
// Abort Multipart Upload Tests
// ---------------------------------------------------------------

it('prevents unauthenticated users from aborting multipart upload', function (): void {
    $response = postJson(route('visa-applications.files.multipart.abort', $this->visaApplication), [
        'upload_id' => 'test-upload-id',
    ]);

    $response->assertUnauthorized();
});

it('prevents users from aborting upload for another users application', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.abort', $this->otherUserApplication), [
            'upload_id' => 'test-upload-id',
        ]);

    $response->assertForbidden();
});

it('validates upload id for abort', function (): void {
    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.abort', $this->visaApplication), []);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.details.upload_id', fn($value) => !empty($value));
});

it('aborts multipart upload successfully', function (): void {
    // Mock the DirectFileUploadService
    $mockService = Mockery::mock(DirectFileUploadService::class);
    $mockService->shouldReceive('abortMultipartUpload')
        ->once()
        ->with('test-upload-id')
        ->andReturnNull();

    $this->app->instance(DirectFileUploadService::class, $mockService);

    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.abort', $this->visaApplication), [
            'upload_id' => 'test-upload-id',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Multipart upload aborted successfully',
            ],
        ]);
});

// ---------------------------------------------------------------
// Error Handling Tests
// ---------------------------------------------------------------

it('handles service exceptions gracefully on initiate', function (): void {
    // Mock the DirectFileUploadService to throw an exception
    $mockService = Mockery::mock(DirectFileUploadService::class);
    $mockService->shouldReceive('initiateMultipartUpload')
        ->once()
        ->andThrow(new \Exception('MinIO connection failed'));

    $this->app->instance(DirectFileUploadService::class, $mockService);

    $response = actingAs($this->user, 'web')
        ->postJson(route('visa-applications.files.multipart.initiate', $this->visaApplication), [
            'file_category_id' => $this->fileCategory->id,
            'file_name' => 'test.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'total_parts' => 2,
        ]);

    $response->assertStatus(500)
        ->assertJson([
            'errors' => [
                'message' => 'Failed to initiate multipart upload',
                'details' => ['error' => 'MinIO connection failed'],
            ],
        ]);
});

