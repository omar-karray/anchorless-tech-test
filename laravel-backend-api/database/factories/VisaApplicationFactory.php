<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisaApplication>
 */
class VisaApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VisaApplication>
     */
    protected $model = VisaApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['draft', 'submitted', 'approved', 'rejected']);

        return [
            'applicant_id' => User::factory(),
            'country' => strtoupper(fake()->countryCode()),
            'status' => $status,
            'submitted_at' => in_array($status, ['submitted', 'approved'], true)
                ? now()->subDays(fake()->numberBetween(1, 10))
                : null,
        ];
    }
}
