<?php

namespace App\Application\Crm;

/** Normalized contact keys used to detect the same person across inquiries. */
final class LeadKeys
{
    public static function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** Last 10 digits of a North American number (leading country code 1 removed). */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }
}
