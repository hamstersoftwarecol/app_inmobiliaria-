<?php

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;

class PropertyMediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(int) config('app_inmobiliaria.media.max_kilobytes', 15360),
                'dimensions:min_width=640,min_height=480,max_width=10000,max_height=10000',
            ],
            'caption' => ['nullable', 'string', 'max:300'],
        ];
    }
}
