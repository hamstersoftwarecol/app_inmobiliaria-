<?php

namespace App\Domain\Deals\Enums;

enum MilestoneType: string
{
    case AttorneyReview = 'attorney_review';
    case Inspection = 'inspection';
    case Appraisal = 'appraisal';
    case MortgageCommitment = 'mortgage_commitment';
    case TitleSearch = 'title_search';
    case FinalWalkthrough = 'final_walkthrough';
    case Closing = 'closing';
    case Custom = 'custom';

    public function title(): string
    {
        return match ($this) {
            self::AttorneyReview => 'Attorney review period',
            self::Inspection => 'Home inspection',
            self::Appraisal => 'Appraisal',
            self::MortgageCommitment => 'Mortgage commitment',
            self::TitleSearch => 'Title search',
            self::FinalWalkthrough => 'Final walk-through',
            self::Closing => 'Closing',
            self::Custom => 'Milestone',
        };
    }
}
