<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiErrorResource;
use App\Http\Resources\ApiSuccessResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

abstract class BaseApiController extends Controller
{
    /**
     * Wrap data in the standard success envelope.
     *
     * @param  Arrayable|JsonResource|array|null  $data
     */
    protected function apiSuccess(
        Arrayable|JsonResource|array|null $data,
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $resource = ApiSuccessResource::make($data);

        if (! empty($meta)) {
            $resource->additional(['meta' => $meta]);
        }

        return $resource->response()->setStatusCode($status);
    }

    /**
     * Wrap an error payload in the standard envelope.
     *
     * @param  array<string, mixed>|string  $message
     */
    protected function apiError(
        array|string $message,
        int $status,
        array $details = [],
        array $meta = []
    ): JsonResponse {
        $payload = [
            'message' => is_string($message) ? $message : ($message['message'] ?? 'Error'),
            'details' => $details ?: ($message['details'] ?? []),
        ];

        $resource = ApiErrorResource::make($payload);

        if (! empty($meta)) {
            $resource->additional(['meta' => $meta]);
        }

        return $resource->response()->setStatusCode($status);
    }
}
