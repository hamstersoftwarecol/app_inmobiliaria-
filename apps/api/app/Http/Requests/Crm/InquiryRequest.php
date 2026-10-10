<?php

namespace App\Http\Requests\Crm;

use App\Domain\Crm\Enums\LeadIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public contact form. `website` is a honeypot (must stay empty); `contact_consent` records the
 * visitor's permission to be contacted about the inquiry (marketing consent is separate).
 */
class InquiryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required_without:phone', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'intent' => ['nullable', Rule::enum(LeadIntent::class)],
            'property_slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9-]+$/'],
            'agent_slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9-]+$/'],
            'preferred_language' => ['nullable', Rule::in(['en', 'es'])],
            'contact_consent' => ['accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}
