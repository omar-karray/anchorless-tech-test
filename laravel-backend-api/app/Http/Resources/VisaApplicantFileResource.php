<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisaApplicantFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visa_application' => $this->whenLoaded('visaApplication', function () {
                return [
                    'id' => $this->visaApplication->id,
                    'country' => $this->visaApplication->country,
                    'status' => $this->visaApplication->status,
                ];
            }),
            'original_name' => $this->original_name,
            'stored_name' => $this->stored_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'path' => $this->path,
            'disk' => $this->disk,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
