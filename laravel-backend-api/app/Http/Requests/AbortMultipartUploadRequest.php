<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AbortMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Gate
    }

    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'Upload ID is required to abort the upload.',
        ];
    }
}
