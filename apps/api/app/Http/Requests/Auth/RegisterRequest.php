<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['nullable', Rule::in(['en', 'es'])],
            'account_type' => ['nullable', Rule::in(config('app_inmobiliaria.security.self_registration_roles'))],
            'device_name' => ['nullable', 'string', 'max:100'],
            'accept_terms' => ['accepted'],
        ];
    }
}
