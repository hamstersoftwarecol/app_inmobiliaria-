<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMlsConnectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'base_url' => ['required', 'url:https', 'max:255'],
            'token_url' => ['nullable', 'url:https', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.client_id' => ['required_without:credentials.access_token', 'nullable', 'string', 'max:500'],
            'credentials.client_secret' => ['required_with:credentials.client_id', 'nullable', 'string', 'max:1000'],
            'credentials.scope' => ['nullable', 'string', 'max:255'],
            'credentials.access_token' => ['required_without:credentials.client_id', 'nullable', 'string', 'max:4000'],
        ];
    }
}
