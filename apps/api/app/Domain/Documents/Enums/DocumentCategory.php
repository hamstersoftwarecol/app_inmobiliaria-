<?php

namespace App\Domain\Documents\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Disclosure = 'disclosure';
    case Inspection = 'inspection';
    case Appraisal = 'appraisal';
    case Financing = 'financing';
    case Title = 'title';
    case Closing = 'closing';
    case Identification = 'identification';
    case Other = 'other';
}
