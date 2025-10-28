<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('creates an API token with valid credentials', function (): void {
    $password = 'secret-123';

    $user = User::factory()->create([
        'password' => bcrypt($password),
    ]);

    $response = postJson('/api/auth/token/create', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'pest-tests',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
            ],
            'errors',
        ])
        ->assertJson([
            'errors' => null,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects invalid credentials for token creation', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
    ]);

    $response = postJson('/api/auth/token/create', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'pest-tests',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'data' => null,
            'errors' => [
                'message' => 'Invalid credentials.',
                'details' => [],
            ],
        ]);
});
