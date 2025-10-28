<?php

namespace App\Http\Controllers;

use App\Events\TestBroadcasted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TestBroadcastController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $message = $request->input('message', sprintf(
            'Test broadcast #%s',
            Str::upper(Str::random(6))
        ));

        TestBroadcasted::dispatch($message);

        return $this->apiSuccess([
            'message' => 'Broadcast dispatched.',
            'payload' => [
                'status' => 'ok',
                'message' => $message,
            ],
        ]);
    }
}
