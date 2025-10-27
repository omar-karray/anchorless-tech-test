<?php

use App\Mail\VisaApplicationSubmittedMail;
use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Mail;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filesystems.default', 'local');

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
    ])->each(function (array $attributes): void {
        FileCategory::factory()->state($attributes)->create();
    });
});

it('lists visa applications belonging to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownApplications = VisaApplication::factory()
        ->count(2)
        ->for($user, 'applicant')
        ->create();

    VisaApplication::factory()->for($otherUser, 'applicant')->create();

    Sanctum::actingAs($user);

    $response = getJson('/api/visa-applications');

    $response->assertOk()
        ->assertJson(['errors' => null]);

    $ids = collect($response->json('data'))->pluck('id')->sort()->values();

    expect($ids)->toEqualCanonicalizing($ownApplications->pluck('id')->sort()->values());
});

it('shows a single visa application with related files', function (): void {
    $user = User::factory()->create();

    $application = VisaApplication::factory()
        ->for($user, 'applicant')
        ->create([
            'country' => 'FR',
            'status' => 'submitted',
        ]);

    $categories = FileCategory::query()->take(2)->get();
    $firstCategoryId = $categories->get(0)?->id ?? FileCategory::query()->value('id');
    $secondCategoryId = $categories->get(1)?->id ?? $firstCategoryId;

    VisaApplicantFile::factory()
        ->count(2)
        ->for($application)
        ->for($user, 'applicant')
        ->state(new Sequence(
            ['file_category_id' => $firstCategoryId],
            ['file_category_id' => $secondCategoryId],
        ))
        ->create();

    Sanctum::actingAs($user);

    $response = getJson("/api/visa-applications/{$application->id}");

    $response->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'id' => $application->id,
                'applicant_id' => $user->id,
                'country' => 'FR',
                'status' => 'submitted',
            ],
        ]);

    expect($response->json('data.files'))->toHaveCount(2);
});

it('prevents accessing applications belonging to another user', function (): void {
    $user = User::factory()->create();
    $otherApplication = VisaApplication::factory()->create();

    Sanctum::actingAs($user);

    $response = getJson("/api/visa-applications/{$otherApplication->id}");

    $response->assertForbidden()
        ->assertJson([
            'data' => null,
            'errors' => [
                'message' => 'You are not allowed to access this visa application.',
            ],
        ]);
});

it('allows deleting an application and cascades file removal', function (): void {
    $user = User::factory()->create();

    $application = VisaApplication::factory()
        ->for($user, 'applicant')
        ->create();

    $categoryId = FileCategory::query()->value('id');

    Storage::fake(config('filesystems.default', 'local'));

    $file = VisaApplicantFile::factory()
        ->for($application)
        ->for($user, 'applicant')
        ->state(['file_category_id' => $categoryId])
        ->create();

    Storage::disk(config('filesystems.default', 'local'))->put($file->path, 'dummy');

    Sanctum::actingAs($user);

    $response = deleteJson("/api/visa-applications/{$application->id}");

    $response->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'message' => 'Visa application deleted successfully.',
                'deleted' => true,
            ],
        ]);

    expect(VisaApplication::query()->find($application->id))->toBeNull();
    expect(VisaApplicantFile::query()->find($file->id))->toBeNull();
});

it('creates a visa application with draft status by default', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = postJson('/api/visa-applications', [
        'country' => 'CA',
    ]);

    $response->assertCreated()
        ->assertJson([
            'errors' => null,
            'data' => [
                'applicant_id' => $user->id,
                'country' => 'CA',
                'status' => 'draft',
            ],
        ]);

    $applicationId = $response->json('data.id');

    expect($applicationId)->not->toBeNull();
    expect(VisaApplication::query()->find($applicationId)->status)->toBe('draft');
});

it('dispatches notification when creating a submitted application', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    Mail::fake();

    $response = postJson('/api/visa-applications', [
        'country' => 'FR',
        'status' => 'submitted',
    ]);

    $response->assertCreated();

    $applicationId = $response->json('data.id');
    expect($applicationId)->not->toBeNull();

    Mail::assertQueued(VisaApplicationSubmittedMail::class, function ($mail) use ($applicationId) {
        return $mail->visaApplication->id === $applicationId;
    });
});

it('updates status to submitted and sets submitted_at automatically', function (): void {
    $user = User::factory()->create();
    $application = VisaApplication::factory()
        ->for($user, 'applicant')
        ->create(['status' => 'draft', 'submitted_at' => null]);

    Sanctum::actingAs($user);

    Mail::fake();

    $response = putJson("/api/visa-applications/{$application->id}", [
        'status' => 'submitted',
    ]);

    $response->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'id' => $application->id,
                'status' => 'submitted',
            ],
        ]);

    $application->refresh();

    expect($application->status)->toBe('submitted');
    expect($application->submitted_at)->not->toBeNull();

    Mail::assertQueued(VisaApplicationSubmittedMail::class, function ($mail) use ($application) {
        return $mail->visaApplication->id === $application->id;
    });
});

it('rejects invalid status values on update', function (): void {
    $user = User::factory()->create();
    $application = VisaApplication::factory()
        ->for($user, 'applicant')
        ->create(['status' => 'draft']);

    Sanctum::actingAs($user);

    $response = putJson("/api/visa-applications/{$application->id}", [
        'status' => 'h',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'data' => null,
            'errors' => [
                'message' => 'Validation failed',
                'details' => [
                    'status' => ['The selected status is invalid.'],
                ],
            ],
        ]);
});
