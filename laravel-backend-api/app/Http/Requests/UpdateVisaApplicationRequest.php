<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisaApplicationRequest extends FormRequest
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
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,submitted,approved,rejected'],
            'submitted_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country') && is_string($this->input('country'))) {
            $this->merge([
                'country' => strtoupper($this->input('country')),
            ]);
        }
    }
}
