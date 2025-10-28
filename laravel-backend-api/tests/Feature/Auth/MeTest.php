<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns the authenticated user via session (cookie) on /api/auth/me', function (): void {
    $user = User::factory()->create();

    actingAs($user); // web guard session

    getJson('/api/auth/me')
        ->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'email' => $user->email,
                'name' => $user->name,
                'id' => $user->id,
            ],
        ]);
});

it('requires authentication on /api/auth/me', function (): void {
    getJson('/api/auth/me')->assertStatus(401);
});

