<?php

namespace App\Http\Requests\Crm;

use App\Domain\Crm\Enums\LeadIntent;
use App\Domain\Crm\Enums\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'source' => [$creating ? 'required' : 'prohibited', Rule::in(array_map(fn ($s) => $s->value, LeadSource::manual()))],
            'intent' => ['sometimes', 'nullable', Rule::enum(LeadIntent::class)],
            'message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'property_id' => ['sometimes', 'nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'budget_min' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'budget_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999', 'gte:budget_min'],
            'preferences' => ['sometimes', 'nullable', 'array', 'max:20'],
            'preferences.*' => ['nullable', 'string', 'max:200'],
            'preferred_language' => ['sometimes', 'nullable', Rule::in(['en', 'es'])],
            'contact_consent' => [$creating ? 'sometimes' : 'prohibited', 'boolean'],
            'assigned_agent_id' => [$creating ? 'sometimes' : 'prohibited', 'nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->isMethod('POST') && blank($this->input('email')) && blank($this->input('phone'))) {
                $v->errors()->add('email', 'Enter an e-mail address or a phone number.');
            }
        });
    }
}
