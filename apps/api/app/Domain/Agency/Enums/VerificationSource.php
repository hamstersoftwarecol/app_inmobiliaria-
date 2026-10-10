<?php

namespace App\Domain\Agency\Enums;

/**
 * How a verifier confirmed a licence. Recorded with a reference (e.g. lookup
 * date/URL, document id) so the decision is auditable.
 */
enum VerificationSource: string
{
    /** Public licensee search of the NJ Real Estate Commission (performed manually by staff). */
    case StateLicenseLookup = 'state_license_lookup';
    /** Review of the licence certificate/pocket card supplied by the agent. */
    case DocumentReview = 'document_review';
    /** Written confirmation from the regulator. */
    case RegulatorConfirmation = 'regulator_confirmation';
}
