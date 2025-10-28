<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('allows accessing protected endpoints with a valid token and revokes it', function (): void {
    // Ensure Sanctum does NOT fall back to session guard during this test
    config()->set('sanctum.guard', []);

    $password = 'secret-123';
    $user = User::factory()->create([
        'password' => bcrypt($password),
    ]);

    // Create a token
    $create = postJson('/api/auth/token/create', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'pest-tests',
    ])->assertOk();

    $token = $create->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    // Can access token-only endpoint with Bearer token
    getJson('/api/auth/me-token', [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'Cookie' => '', // ensure no session cookie interferes
        'Origin' => 'http://external.test',
        'Referer' => 'http://external.test',
    ])->assertOk()->assertJsonPath('data.email', $user->email);

    // Revoke current token
    postJson('/api/auth/token/revoke', [], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'Cookie' => '',
        'Origin' => 'http://external.test',
        'Referer' => 'http://external.test',
    ])->assertOk()->assertJsonPath('data.revoked', true);

    // Ensure token row is actually deleted
    expect(PersonalAccessToken::findToken($token))->toBeNull();

    // Token should no longer authenticate (token-only endpoint)
    getJson('/api/auth/me-token', [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'Cookie' => '',
        'Origin' => 'http://external.test',
        'Referer' => 'http://external.test',
    ])->assertStatus(401);
});
