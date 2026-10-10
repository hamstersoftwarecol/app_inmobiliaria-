<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'license_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'website' => ['nullable', 'url:https', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone:per_country,US'],
        ];
    }
}
