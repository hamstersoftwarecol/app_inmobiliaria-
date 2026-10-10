<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)->withoutTrashed()],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'locale' => ['sometimes', 'required', Rule::in(['en', 'es'])],
            'timezone' => ['sometimes', 'required', 'timezone:per_country,US'],
            'current_password' => ['required_with:email', 'current_password'],
        ];
    }
}
