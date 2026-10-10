<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'locale' => ['nullable', Rule::in(['en', 'es'])],
            'timezone' => ['nullable', 'timezone:per_country,US'],
            'password' => ['required', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
        ];
    }
}
