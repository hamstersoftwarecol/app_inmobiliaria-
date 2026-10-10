<?php

namespace App\Domain\Deals\Enums;

enum Contingency: string
{
    case AttorneyReview = 'attorney_review';
    case Inspection = 'inspection';
    case Financing = 'financing';
    case Appraisal = 'appraisal';
    case SaleOfHome = 'sale_of_home';
    case HoaReview = 'hoa_review';
}
