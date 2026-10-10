<?php

namespace App\Http\Resources;

use App\Application\Mls\MLSComplianceService;
use App\Domain\Agency\Enums\AgentStatus;
use App\Models\MlsDisplayRule;
use App\Models\Property;
use App\Models\PropertyMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Public listing output. For MLS listings it enforces the provider rules at
 * render time: allowed fields, address suppression, photo permissions and
 * limits, mandatory attribution/disclaimer and data freshness.
 *
 * @mixin Property
 */
class PublicPropertyResource extends JsonResource
{
    /** Detail fields subject to MLS `allowed_fields` restrictions. */
    private const RESTRICTABLE = [
        'description', 'bedrooms', 'bathrooms', 'living_area_sqft', 'lot_size_sqft', 'year_built',
        'parking_spaces', 'garage_spaces', 'heating', 'cooling', 'amenities', 'hoa_fee_monthly', 'annual_taxes',
    ];

    private bool $summary = false;

    public function summary(): self
    {
        $this->summary = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rules = $this->isMls() ? $this->mlsListing?->provider?->displayRule : null;
        $compliance = app(MLSComplianceService::class);
        $es = $request->getPreferredLanguage(['en', 'es']) === 'es';
        $showAddress = $this->address_display;

        $details = [
            'description' => $es && $this->description_es ? $this->description_es : $this->description,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms !== null ? (float) $this->bathrooms : null,
            'living_area_sqft' => $this->living_area_sqft,
            'lot_size_sqft' => $this->lot_size_sqft !== null ? (float) $this->lot_size_sqft : null,
            'year_built' => $this->year_built,
            'parking_spaces' => $this->parking_spaces,
            'garage_spaces' => $this->garage_spaces,
            'heating' => $this->heating ?? [],
            'cooling' => $this->cooling ?? [],
            'amenities' => $this->amenities ?? [],
            'hoa_fee_monthly' => $this->hoa_fee_monthly !== null ? (float) $this->hoa_fee_monthly : null,
            'annual_taxes' => $this->annual_taxes !== null ? (float) $this->annual_taxes : null,
        ];
        if ($rules !== null) {
            $allowed = $compliance->filterFields($details, $rules);
            $details = array_map(fn () => null, $details);
            $details = array_merge($details, $allowed);
        }

        $media = $this->relationLoaded('media') ? $this->publicMedia($rules) : collect();

        $agent = $this->relationLoaded('listingAgent') ? $this->listingAgent : null;
        $agentIsPublic = $agent && $agent->status === AgentStatus::Active && $agent->is_public;

        $base = [
            'slug' => $this->slug,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'listing_type' => $this->listing_type->value,
            'property_type' => $this->propertyType ? ['code' => $this->propertyType->code, 'name' => $es ? $this->propertyType->name_es : $this->propertyType->name_en] : null,
            'title' => $this->title,
            'list_price' => $this->list_price !== null ? (float) $this->list_price : null,
            'currency' => $this->currency,
            'address' => [
                'street_address' => $showAddress ? $this->street_address : null,
                'unit' => $showAddress ? $this->unit : null,
                'city' => $this->city,
                'county' => $this->county,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'displayed' => $showAddress,
            ],
            'location' => $showAddress && $this->latitude !== null ? ['latitude' => $this->latitude, 'longitude' => $this->longitude] : null,
            'bedrooms' => $details['bedrooms'],
            'bathrooms' => $details['bathrooms'],
            'living_area_sqft' => $details['living_area_sqft'],
            'cover_photo' => $media->first()?->publicUrl(),
            'compliance' => $this->isMls() ? [
                'source_mls' => $this->mlsListing?->provider?->name,
                'listing_id' => $this->mlsListing?->listing_id,
                'attribution' => $this->attribution($compliance, $rules),
                'disclaimer' => $rules?->disclaimer,
                'last_updated_at' => $this->mlsListing?->last_synced_at?->toIso8601String(),
            ] : null,
        ];

        if ($this->summary) {
            return $base;
        }

        return $base + [
            'details' => $details,
            'photos' => $media->map(fn (PropertyMedia $m) => ['url' => $m->publicUrl(), 'caption' => $m->caption, 'width' => $m->width, 'height' => $m->height])->values(),
            'listing_agent' => $agentIsPublic ? [
                'full_name' => $agent->user?->full_name,
                'slug' => $agent->slug,
                'phone' => $agent->public_phone,
                'email' => $agent->public_email,
            ] : null,
            'brokerage' => $this->agency ? ['name' => $this->agency->name, 'license_number' => $this->agency->license_number] : null,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, PropertyMedia>
     */
    private function publicMedia(?MlsDisplayRule $rules): Collection
    {
        $items = $this->media->filter(fn (PropertyMedia $m) => $m->display_allowed)->values();
        $max = $rules?->photo_rules['max_photos'] ?? null;

        return $max !== null ? $items->take((int) $max) : $items;
    }

    private function attribution(MLSComplianceService $compliance, ?MlsDisplayRule $rules): ?string
    {
        $listing = $this->mlsListing;
        if ($listing === null) {
            return null;
        }
        $template = $rules?->attribution_template;

        return $template !== null
            ? strtr($template, ['{broker}' => (string) $listing->listing_broker, '{agent}' => (string) $listing->listing_agent])
            : ($listing->attribution ?? ($listing->listing_broker ? "Listing courtesy of {$listing->listing_broker}" : null));
    }
}
