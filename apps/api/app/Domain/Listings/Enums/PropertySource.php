<?php

namespace App\Domain\Listings\Enums;

enum PropertySource: string
{
    /** Created and maintained by the agency. */
    case Internal = 'internal';
    /** Materialized from an authorized MLS feed; MLS-owned fields are read-only. */
    case Mls = 'mls';
}
