<?php

namespace Database\Seeders;

use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseTestSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->ensureFileCategories();

        $user = User::firstOrCreate(
            ['email' => 'demo@anchorless.dev'],
            [
                'name' => 'Demo Applicant',
                'password' => Hash::make('password'),
            ]
        );

        $application = VisaApplication::firstOrCreate(
            [
                'applicant_id' => $user->id,
                'country' => 'FR',
            ],
            [
                'status' => 'submitted',
                'submitted_at' => now()->subDay(),
            ]
        );

        $files = [
            [
                'category' => $categories->get('passport'),
                'original_name' => 'passport.pdf',
                'mime_type' => 'application/pdf',
            ],
            [
                'category' => $categories->get('visa_application_form'),
                'original_name' => 'visa-application-form.pdf',
                'mime_type' => 'application/pdf',
            ],
            [
                'category' => $categories->get('id_photo'),
                'original_name' => 'id-photo.jpg',
                'mime_type' => 'image/jpeg',
            ],
        ];

        foreach ($files as $fileData) {
            if (! $fileData['category']) {
                continue;
            }

            $storedName = Str::uuid()->toString() . '.' . pathinfo($fileData['original_name'], PATHINFO_EXTENSION);

            VisaApplicantFile::firstOrCreate(
                [
                    'visa_application_id' => $application->id,
                    'original_name' => $fileData['original_name'],
                ],
                [
                    'applicant_id' => $user->id,
                    'file_category_id' => $fileData['category']->id,
                    'stored_name' => $storedName,
                    'mime_type' => $fileData['mime_type'],
                    'size_bytes' => fake()->numberBetween(80_000, 220_000),
                    'path' => sprintf('visa-applications/%s/files/%s', $application->id, $storedName),
                    'disk' => config('filesystems.default', 'local'),
                ]
            );
        }

        $otherUser = User::firstOrCreate(
            ['email' => 'example@anchorless.dev'],
            [
                'name' => 'Second Applicant',
                'password' => Hash::make('password'),
            ]
        );

        $otherApplication = VisaApplication::firstOrCreate(
            [
                'applicant_id' => $otherUser->id,
                'country' => 'CA',
            ],
            [
                'status' => 'draft',
                'submitted_at' => null,
            ]
        );

        $secondaryStoredName = Str::uuid()->toString() . '.pdf';

        VisaApplicantFile::firstOrCreate(
            [
                'visa_application_id' => $otherApplication->id,
                'original_name' => 'secondary-passport.pdf',
            ],
            [
                'applicant_id' => $otherUser->id,
                'file_category_id' => $categories->get('passport')?->id ?? $categories->first()->id,
                'stored_name' => $secondaryStoredName,
                'mime_type' => 'application/pdf',
                'size_bytes' => fake()->numberBetween(80_000, 220_000),
                'path' => sprintf('visa-applications/%s/files/%s', $otherApplication->id, $secondaryStoredName),
                'disk' => config('filesystems.default', 'local'),
            ]
        );
    }

    /**
     * Ensure the expected file categories exist and return them keyed by slug.
     *
     * @return Collection<string, \App\Models\FileCategory>
     */
    private function ensureFileCategories(): Collection
    {
        $payload = [
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
        ];

        return collect($payload)->mapWithKeys(function (array $data) {
            $category = FileCategory::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            return [$category->slug => $category];
        });
    }
}
