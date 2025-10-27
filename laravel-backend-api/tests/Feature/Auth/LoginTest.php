<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('authenticates a user with valid credentials', function (): void {
    $password = 'secret-123';

    $user = User::factory()->create([
        'password' => bcrypt($password),
    ]);

    $response = postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
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

it('rejects invalid credentials', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
    ]);

    $response = postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
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
