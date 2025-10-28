<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Gate
    }

    public function rules(): array
    {
        return [
            'file_category_id' => ['required', 'integer', 'exists:file_categories,id'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:524288000'], // Max 500MB
            'mime_type' => ['required', 'string', 'max:100'],
            'total_parts' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file_category_id.required' => 'File category is required.',
            'file_category_id.exists' => 'Invalid file category.',
            'file_name.required' => 'File name is required.',
            'file_size.required' => 'File size is required.',
            'file_size.max' => 'File size cannot exceed 500MB.',
            'total_parts.required' => 'Total parts is required.',
            'total_parts.max' => 'Total parts cannot exceed 10000.',
        ];
    }
}
