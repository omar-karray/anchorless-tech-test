<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FileCategory;

class FileCategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        FileCategory::factory()->createMany([
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
            [
                'name' => 'Passport',
                'slug' => 'passport',
                'description' => 'Scanned passport copy',
            ],
            [
                'name' => 'Proof of Address',
                'slug' => 'proof_of_address',
                'description' => 'Document proving address',
            ],
        ]);
    }
}
