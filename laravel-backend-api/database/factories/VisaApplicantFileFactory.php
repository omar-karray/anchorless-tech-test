<?php

namespace Database\Factories;

use App\Models\FileCategory;
use App\Models\User;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VisaApplicantFile>
 */
class VisaApplicantFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VisaApplicantFile>
     */
    protected $model = VisaApplicantFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = fake()->randomElement([
            ['extension' => 'pdf', 'mime' => 'application/pdf'],
            ['extension' => 'png', 'mime' => 'image/png'],
            ['extension' => 'jpg', 'mime' => 'image/jpeg'],
        ]);

        $storedName = Str::uuid()->toString() . '.' . $file['extension'];

        return [
            'visa_application_id' => VisaApplication::factory(),
            'applicant_id' => User::factory(),
            'file_category_id' => FileCategory::factory(),
            'original_name' => fake()->unique()->words(2, true) . '.' . $file['extension'],
            'stored_name' => $storedName,
            'mime_type' => $file['mime'],
            'size_bytes' => fake()->numberBetween(50_000, 3_500_000),
            'path' => null,
            'disk' => config('filesystems.default', 'local'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (VisaApplicantFile $file): void {
            if ($file->visa_application_id) {
                $file->path = sprintf('visa-applications/%s/files/%s', $file->visa_application_id, $file->stored_name);
            }
        })->afterCreating(function (VisaApplicantFile $file): void {
            $file->path = sprintf('visa-applications/%s/files/%s', $file->visa_application_id, $file->stored_name);
            $file->save();
        });
    }
}
