<?php

namespace App\Application\Mls;

use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\DTOs\MLSMediaDTO;
use App\Domain\Mls\Enums\ListingStatus;
use App\Models\MlsDisplayRule;

/**
 * Evaluates per-provider IDX/display rules. Absent rules mean "deny":
 * nothing from an MLS is shown until its rules are configured.
 */
class MLSComplianceService
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    public function canDisplay(MLSListingDTO $listing, ?MlsDisplayRule $rules, string $channel = self::CHANNEL_WEB): bool
    {
        if ($rules === null || ! $listing->internetDisplayAllowed) {
            return false;
        }

        $channelAllowed = match ($channel) {
            self::CHANNEL_MOBILE => $rules->allow_public_display && $rules->allow_mobile_display,
            default => $rules->allow_public_display,
        };
        if (! $channelAllowed) {
            return false;
        }

        $status = ListingStatus::tryFrom($listing->status) ?? ListingStatus::Unknown;

        return ! ($rules->hide_withdrawn_immediately && $status->isOffMarket()) && $status !== ListingStatus::Unknown;
    }

    public function canProcessWithAi(?MlsDisplayRule $rules): bool
    {
        return $rules !== null && $rules->allow_ai_processing;
    }

    public function canDisplayMedia(MLSMediaDTO $media, ?MlsDisplayRule $rules): bool
    {
        if ($rules === null || ! $media->displayAllowed) {
            return false;
        }

        $max = $rules->photo_rules['max_photos'] ?? null;

        return $max === null || $media->order < (int) $max;
    }

    /**
     * Restricts a field set to those the licence allows (null = all normalized fields).
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function filterFields(array $fields, ?MlsDisplayRule $rules): array
    {
        $allowed = $rules?->allowed_fields;

        return $allowed === null ? $fields : array_intersect_key($fields, array_flip($allowed));
    }

    public function attribution(MLSListingDTO $listing, ?MlsDisplayRule $rules): ?string
    {
        $template = $rules?->attribution_template;

        return $template !== null
            ? strtr($template, ['{broker}' => (string) $listing->listBrokerName, '{agent}' => (string) $listing->listAgentName])
            : $listing->attribution;
    }
}
