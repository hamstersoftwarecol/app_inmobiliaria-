<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMlsProviderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('mls_providers', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'driver' => ['required', 'string', Rule::in(array_keys(config('mls.drivers')))],
            'protocol' => ['required', Rule::in(['reso_web_api', 'rets', 'other'])],
            'website' => ['nullable', 'url:https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'display_rule' => ['sometimes', 'array'],
            'display_rule.attribution_template' => ['nullable', 'string', 'max:1000'],
            'display_rule.disclaimer' => ['nullable', 'string', 'max:5000'],
            'display_rule.min_refresh_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'display_rule.retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'display_rule.allowed_fields' => ['nullable', 'array'],
            'display_rule.allowed_fields.*' => ['string', 'max:100'],
            'display_rule.photo_rules' => ['nullable', 'array'],
            'display_rule.photo_rules.max_photos' => ['nullable', 'integer', 'min:0', 'max:500'],
            'display_rule.allow_public_display' => ['sometimes', 'boolean'],
            'display_rule.allow_mobile_display' => ['sometimes', 'boolean'],
            'display_rule.allow_ai_processing' => ['sometimes', 'boolean'],
            'display_rule.hide_withdrawn_immediately' => ['sometimes', 'boolean'],
        ];
    }
}
