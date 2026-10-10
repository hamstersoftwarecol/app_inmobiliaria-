<?php

namespace App\Http\Requests\Concerns;

/**
 * E-mails are stored lower-cased; normalize before validation so uniqueness
 * and lookups are case-insensitive.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
