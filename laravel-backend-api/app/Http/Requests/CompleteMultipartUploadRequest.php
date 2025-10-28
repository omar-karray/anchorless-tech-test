<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Gate
    }

    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'max:255'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.part_number' => ['required', 'integer', 'min:1'],
            'parts.*.etag' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'Upload ID is required.',
            'parts.required' => 'Parts information is required.',
            'parts.min' => 'At least one part is required.',
            'parts.*.part_number.required' => 'Part number is required for each part.',
            'parts.*.etag.required' => 'ETag is required for each part.',
        ];
    }
}
