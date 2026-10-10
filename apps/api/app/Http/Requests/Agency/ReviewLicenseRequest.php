<?php

namespace App\Http\Requests\Agency;

use App\Domain\Agency\Enums\VerificationSource;
use App\Support\AgencyClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLicenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'verify' => [
                'verification_source' => ['required', Rule::enum(VerificationSource::class)],
                'verification_reference' => ['required', 'string', 'min:3', 'max:255'],
                'expires_on' => ['required', 'date', 'after_or_equal:'.AgencyClock::todayString()],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            default => [
                'reason' => ['required', 'string', 'min:5', 'max:2000'],
            ],
        };
    }
}
