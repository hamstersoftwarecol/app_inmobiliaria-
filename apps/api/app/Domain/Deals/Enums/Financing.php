<?php

namespace App\Domain\Deals\Enums;

enum Financing: string
{
    case Cash = 'cash';
    case Conventional = 'conventional';
    case Fha = 'fha';
    case Va = 'va';
    case Usda = 'usda';
    case Other = 'other';
}
