<?php

use App\Jobs\StoreVisaApplicantFile;
use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\delete;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filesystems.default', 'local');
});

function seedFileCategories(): void
{
    collect([
        [
            'name' => 'Passport',
            'slug' => 'passport',
            'description' => 'Scanned passport copy',
        ],
        [
            'name' => 'Visa Application Form',
            'slug' => 'visa_application_form',
            'description' => 'Filled visa application form',
        ],
        [
            'name' => 'ID Photo',
            'slug' => 'id_photo',
            'description' => 'Passport-sized photograph',
        ],
    ])->each(fn (array $attributes) => FileCategory::factory()->state($attributes)->create());
}

it('lists files for a visa application', function (): void {
    seedFileCategories();

    $user = User::factory()->create();
    $application = VisaApplication::factory()->for($user, 'applicant')->create();

    $categories = FileCategory::query()->take(2)->get();
    VisaApplicantFile::factory()
        ->count(2)
        ->for($application)
        ->for($user, 'applicant')
        ->state(new Sequence(
            ['file_category_id' => $categories[0]->id],
            ['file_category_id' => $categories[1]->id],
        ))
        ->create();

    Sanctum::actingAs($user);

    $response = getJson("/api/visa-applications/{$application->id}/files");

    $response->assertOk()
        ->assertJson(['errors' => null]);

    expect($response->json('data'))->toHaveCount(2);
});

it('does not allow listing files for another users application', function (): void {
    seedFileCategories();

    $user = User::factory()->create();
    $otherApplication = VisaApplication::factory()->create();

    Sanctum::actingAs($user);

    $response = getJson("/api/visa-applications/{$otherApplication->id}/files");

    $response->assertForbidden()
        ->assertJson([
            'data' => null,
            'errors' => [
                'message' => 'You are not allowed to access this visa application.',
            ],
        ]);
});

it('queues a file upload for a visa application', function (): void {
    seedFileCategories();

    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $application = VisaApplication::factory()->for($user, 'applicant')->create();
    $category = FileCategory::query()->first();

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

    $response = post(
        "/api/visa-applications/{$application->id}/files",
        [
            'file_category_id' => $category->id,
            'file' => $file,
        ]
    );

    $response->assertAccepted()
        ->assertJson([
            'errors' => null,
            'data' => [
                'message' => 'File upload queued for processing.',
            ],
        ]);

    $dispatchedJob = null;

    Queue::assertPushed(StoreVisaApplicantFile::class, function (StoreVisaApplicantFile $job) use ($application, $category, $user, &$dispatchedJob): bool {
        $dispatchedJob = $job;

        expect($job->visaApplicationId)->toBe($application->id);
        expect($job->applicantId)->toBe($user->id);
        expect($job->fileCategoryId)->toBe($category->id);
        expect($job->queue)->toBe('file-uploads');
        expect($job->temporaryPath)->toMatch("#^tmp/visa-applications/{$application->id}/#");

        Storage::disk($job->temporaryDisk)->assertExists($job->temporaryPath);

        return true;
    });

    expect($dispatchedJob)->not->toBeNull();
    expect(VisaApplicantFile::query()->where('visa_application_id', $application->id)->count())->toBe(0);
});

it('prevents uploading to another users application', function (): void {
    seedFileCategories();

    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $otherApplication = VisaApplication::factory()->create();
    $category = FileCategory::query()->first();

    Sanctum::actingAs($user);

    $response = post(
        "/api/visa-applications/{$otherApplication->id}/files",
        [
            'file_category_id' => $category->id,
            'file' => UploadedFile::fake()->create('id-photo.jpg', 50, 'image/jpeg'),
        ]
    );

    $response->assertForbidden()
        ->assertJson([
            'data' => null,
            'errors' => [
                'message' => 'You are not allowed to access this visa application.',
            ],
        ]);

    Queue::assertNothingPushed();
    Storage::disk('local')->assertDirectoryEmpty('');
    expect(VisaApplicantFile::query()->count())->toBe(0);
});

it('deletes a file tied to a visa application', function (): void {
    seedFileCategories();

    Storage::fake(config('filesystems.default', 'local'));

    $user = User::factory()->create();
    $application = VisaApplication::factory()->for($user, 'applicant')->create();
    $category = FileCategory::query()->first();

    $fileRecord = VisaApplicantFile::factory()
        ->for($application)
        ->for($user, 'applicant')
        ->state([
            'file_category_id' => $category->id,
            'disk' => config('filesystems.default', 'local'),
            'path' => 'visa-applications/'.$application->id.'/files/example.pdf',
        ])
        ->create();

    Storage::disk(config('filesystems.default', 'local'))->put($fileRecord->path, 'dummy');

    Sanctum::actingAs($user);

    $response = delete("/api/visa-applications/{$application->id}/files/{$fileRecord->id}");

    $response->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'message' => 'Visa application file deleted successfully.',
                'deleted' => true,
            ],
        ]);

    expect(VisaApplicantFile::query()->find($fileRecord->id))->toBeNull();
    Storage::disk(config('filesystems.default', 'local'))->assertMissing($fileRecord->path);
});
