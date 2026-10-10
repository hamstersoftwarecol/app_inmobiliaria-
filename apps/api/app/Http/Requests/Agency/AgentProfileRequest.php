<?php

namespace App\Http\Requests\Agency;

use App\Domain\Agency\Enums\AgentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by admin create/update and agent self-service; the controller
 * restricts which keys each audience may change.
 */
class AgentProfileRequest extends FormRequest
{
    public const LANGUAGES = ['en', 'es', 'pt', 'fr', 'it', 'pl', 'zh', 'ko', 'hi', 'gu', 'ar', 'ru', 'ht', 'tl'];

    public const SPECIALTIES = [
        'buyers', 'sellers', 'rentals', 'first_time_buyers', 'luxury', 'condos', 'multi_family',
        'new_construction', 'commercial', 'land', 'relocation', 'investment', 'seniors',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'user_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'agency_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('agencies', 'id')->whereNull('deleted_at')],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'public_email' => ['nullable', 'email:rfc', 'max:255'],
            'public_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'bio_es' => ['nullable', 'string', 'max:5000'],
            'languages' => ['nullable', 'array', 'max:10'],
            'languages.*' => ['string', 'distinct', Rule::in(self::LANGUAGES)],
            'specialties' => ['nullable', 'array', 'max:10'],
            'specialties.*' => ['string', 'distinct', Rule::in(self::SPECIALTIES)],
            'photo_url' => ['nullable', 'url:https', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', Rule::enum(AgentStatus::class)],
        ];
    }
}
