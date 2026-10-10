<?php

namespace App\Domain\Mls\Enums;

enum ProviderStatus: string
{
    /** Default: no signed data licence / official specification yet. */
    case PendingAuthorization = 'pending_authorization';
    /** Credentials stored, sandbox testing only. */
    case Sandbox = 'sandbox';
    /** Licence verified; production sync may be enabled. */
    case Authorized = 'authorized';
    case Suspended = 'suspended';
}
