<?php

use App\Events\TestBroadcasted;
use Illuminate\Support\Facades\Event;
use function Pest\Laravel\postJson;

it('dispatches the test broadcast event with provided message', function (): void {
    Event::fake();

    $response = postJson('/api/diagnostics/broadcast-test', [
        'message' => 'Hello diagnostics',
    ]);

    $response->assertOk()
        ->assertJson([
            'errors' => null,
            'data' => [
                'message' => 'Broadcast dispatched.',
                'payload' => [
                    'status' => 'ok',
                    'message' => 'Hello diagnostics',
                ],
            ],
        ]);

    Event::assertDispatched(TestBroadcasted::class, function (TestBroadcasted $event): bool {
        return $event->message === 'Hello diagnostics';
    });
});

it('generates a default message when none is supplied', function (): void {
    Event::fake();

    $response = postJson('/api/diagnostics/broadcast-test');

    $response->assertOk()
        ->assertJsonPath('data.payload.status', 'ok')
        ->assertJsonPath('data.payload.message', fn ($message) => is_string($message) && str_starts_with($message, 'Test broadcast #'));

    Event::assertDispatched(TestBroadcasted::class);
});
