<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'agency_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('agencies', 'id')->whereNull('deleted_at')],
            'name' => [$required, 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'street_address' => [$required, 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'city' => [$required, 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'required', 'string', 'size:2', 'alpha'],
            'postal_code' => [$required, 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_headquarters' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
