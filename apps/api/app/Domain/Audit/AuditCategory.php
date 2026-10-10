<?php

namespace App\Domain\Audit;

enum AuditCategory: string
{
    case Security = 'security';
    case Admin = 'admin';
    case Data = 'data';
    case Mls = 'mls';
}
