<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FileCategory>
 */
class FileCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            ['name' => 'Visa Application Form', 'slug' => 'visa_application_form', 'description' => 'Filled visa application form'],
            ['name' => 'ID Photo', 'slug' => 'id_photo', 'description' => 'Passport-sized photograph'],
            ['name' => 'Passport', 'slug' => 'passport', 'description' => 'Scanned passport copy'],
            ['name' => 'Proof of Address', 'slug' => 'proof_of_address', 'description' => 'Document proving address'],
        ];
        $category = fake()->randomElement($categories);
        return [
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
        ];
    }
}
