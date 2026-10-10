<?php

namespace App\Http\Requests\Admin;

use App\Domain\Mls\Enums\ProviderStatus;
use Illuminate\Validation\Rule;

class UpdateMlsProviderRequest extends StoreMlsProviderRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['code'], $rules['driver']);

        foreach (['name', 'protocol'] as $field) {
            $rules[$field] = array_merge(['sometimes'], $rules[$field]);
        }

        return $rules + [
            'status' => ['sometimes', Rule::enum(ProviderStatus::class)],
            'is_enabled' => ['sometimes', 'boolean'],
            // Explicit human confirmation that a signed licence exists before authorizing.
            'licence_confirmed' => [Rule::requiredIf(fn () => $this->input('status') === ProviderStatus::Authorized->value || $this->boolean('is_enabled')), 'accepted'],
            'licence_reference' => [Rule::requiredIf(fn () => $this->input('status') === ProviderStatus::Authorized->value), 'nullable', 'string', 'max:255'],
        ];
    }
}
