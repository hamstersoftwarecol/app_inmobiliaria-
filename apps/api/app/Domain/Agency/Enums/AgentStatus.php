<?php

namespace App\Domain\Agency\Enums;

enum AgentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
