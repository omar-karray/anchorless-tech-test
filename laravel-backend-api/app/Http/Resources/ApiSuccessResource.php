<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiSuccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = $this->resource instanceof JsonResource
            ? $this->resource->resolve($request)
            : $this->resource;

        return [
            'data' => $payload,
            'errors' => null,
        ];
    }
}
