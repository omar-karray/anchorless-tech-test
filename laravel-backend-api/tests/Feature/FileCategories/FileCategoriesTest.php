<?php

use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Seed file categories
    collect([
        [
            'name' => 'Visa Application Form',
            'slug' => 'visa_application_form',
            'description' => 'Official visa application form',
        ],
        [
            'name' => 'ID Photo',
            'slug' => 'id_photo',
            'description' => 'Passport-sized photograph',
        ],
        [
            'name' => 'Passport',
            'slug' => 'passport',
            'description' => 'Valid passport copy',
        ],
        [
            'name' => 'Proof of Address',
            'slug' => 'proof_of_address',
            'description' => 'Utility bill or bank statement',
        ],
    ])->each(fn (array $data) => FileCategory::create($data));
});

test('unauthenticated user cannot access file categories', function (): void {
    $response = getJson('/api/file-categories');

    $response->assertUnauthorized();
});

test('authenticated user can get all file categories', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/file-categories');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'created_at',
                    'updated_at',
                ],
            ],
            'errors',
        ])
        ->assertJsonPath('errors', null)
        ->assertJsonCount(4, 'data');
});

test('file categories are returned in alphabetical order by name', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/file-categories');

    $response->assertOk();

    $data = $response->json('data');
    $names = array_column($data, 'name');

    // Check they're sorted alphabetically
    expect($names)->toBe([
        'ID Photo',
        'Passport',
        'Proof of Address',
        'Visa Application Form',
    ]);
});

test('file categories include all required fields', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/file-categories');

    $response->assertOk();

    $category = $response->json('data.0');

    expect($category)->toHaveKeys([
        'id',
        'name',
        'slug',
        'description',
        'created_at',
        'updated_at',
    ]);

    expect($category['id'])->toBeInt();
    expect($category['name'])->toBeString();
    expect($category['slug'])->toBeString();
    expect($category['description'])->toBeString();
    expect($category['created_at'])->toBeString();
    expect($category['updated_at'])->toBeString();
});

test('file categories resource uses ISO 8601 date format', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson('/api/file-categories');

    $response->assertOk();

    $category = $response->json('data.0');

    // ISO 8601 format: 2025-10-28T12:34:56.000000Z
    expect($category['created_at'])
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
    expect($category['updated_at'])
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});
