<?php

use App\Events\VisaApplicantFileFailed;
use App\Events\VisaApplicantFileStored;
use App\Jobs\StoreVisaApplicantFile;
use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('moves a queued visa applicant file from temporary to permanent storage', function (): void {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
    Event::fake();

    $user = User::factory()->create();
    $application = VisaApplication::factory()->for($user, 'applicant')->create();
    $category = FileCategory::factory()->create();

    $temporaryDisk = 'local';
    $temporaryDirectory = "tmp/visa-applications/{$application->id}";
    $uploadedFile = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
    $temporaryPath = $uploadedFile->store($temporaryDirectory, $temporaryDisk);

    $job = new StoreVisaApplicantFile(
        visaApplicationId: $application->id,
        applicantId: $user->id,
        fileCategoryId: $category->id,
        temporaryPath: $temporaryPath,
        temporaryDisk: $temporaryDisk,
        originalName: $uploadedFile->getClientOriginalName(),
        mimeType: $uploadedFile->getClientMimeType(),
        sizeBytes: $uploadedFile->getSize() ?? 0
    );

    $job->handle();

    $storedFile = VisaApplicantFile::query()->first();

    expect($storedFile)->not->toBeNull();
    expect($storedFile->visa_application_id)->toBe($application->id);
    expect($storedFile->applicant_id)->toBe($user->id);
    expect($storedFile->file_category_id)->toBe($category->id);
    expect($storedFile->original_name)->toBe('passport.pdf');
    expect($storedFile->stored_name)->toBe(basename($storedFile->path));

    Storage::disk('local')->assertMissing($temporaryPath);
    Storage::disk($storedFile->disk)->assertExists($storedFile->path);

Event::assertDispatched(VisaApplicantFileStored::class, function (VisaApplicantFileStored $event) use ($storedFile): bool {
        return $event->visaApplicantFile->is($storedFile);
    });
});

it('broadcasts a failure event when the temporary file is missing', function (): void {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
    Event::fake();

    $user = User::factory()->create();
    $application = VisaApplication::factory()->for($user, 'applicant')->create();
    $category = FileCategory::factory()->create();

    $temporaryDisk = 'local';
    $temporaryDirectory = "tmp/visa-applications/{$application->id}";
    $uploadedFile = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
    $temporaryPath = $uploadedFile->store($temporaryDirectory, $temporaryDisk);

    Storage::disk('local')->delete($temporaryPath);

    $job = new StoreVisaApplicantFile(
        visaApplicationId: $application->id,
        applicantId: $user->id,
        fileCategoryId: $category->id,
        temporaryPath: $temporaryPath,
        temporaryDisk: $temporaryDisk,
        originalName: $uploadedFile->getClientOriginalName(),
        mimeType: $uploadedFile->getClientMimeType(),
        sizeBytes: $uploadedFile->getSize() ?? 0
    );

    $job->handle();

    Event::assertDispatched(VisaApplicantFileFailed::class, function (VisaApplicantFileFailed $event) use ($application): bool {
        return $event->visaApplicationId === $application->id
            && $event->reason === 'temporary_file_missing';
    });

    Event::assertNotDispatched(VisaApplicantFileStored::class);

    expect(VisaApplicantFile::query()->count())->toBe(0);
});
