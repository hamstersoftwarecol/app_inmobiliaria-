<?php

namespace App\Domain\Documents;

use App\Domain\Shared\DomainException;

final class DocumentException extends DomainException
{
    public static function notAvailable(string $status): self
    {
        return new self('document_unavailable', $status === 'infected'
            ? 'This file was flagged by the malware scan and cannot be downloaded.'
            : 'This file is still being checked for malware. Try again in a moment.', $status === 'infected' ? 403 : 409, ['scan_status' => $status]);
    }
}
