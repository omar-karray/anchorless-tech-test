<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisaApplicantFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_category_id' => ['required', 'integer', 'exists:file_categories,id'],
            'file' => [
                'required',
                'file',
                'mimetypes:application/pdf,image/png,image/jpeg',
                'max:4096',
            ],
        ];
    }
}
