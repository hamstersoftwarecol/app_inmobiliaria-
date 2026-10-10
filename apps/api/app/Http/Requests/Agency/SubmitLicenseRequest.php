<?php

namespace App\Http\Requests\Agency;

use App\Domain\Agency\Enums\LicenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLicenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Format is checked loosely on purpose: validity is established by verification, not by regex.
            'license_number' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9 -]+$/'],
            'license_state' => ['sometimes', 'required', 'string', 'size:2', 'alpha'],
            'license_type' => ['required', Rule::enum(LicenseType::class)],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issued_on'],
        ];
    }
}
