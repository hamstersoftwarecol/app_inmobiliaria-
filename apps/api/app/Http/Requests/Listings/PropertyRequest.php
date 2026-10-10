<?php

namespace App\Http\Requests\Listings;

use App\Domain\Listings\Enums\ListingType;
use App\Domain\Mls\Enums\ListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    public const AMENITIES = [
        'pool', 'garage', 'basement', 'fireplace', 'central_air', 'laundry_in_unit', 'elevator', 'doorman',
        'gym', 'balcony', 'patio', 'deck', 'waterfront', 'pets_allowed', 'ev_charging', 'solar', 'accessible',
    ];

    /** Statuses staff may set on internal listings. */
    public const INTERNAL_STATUSES = ['active', 'coming_soon', 'active_under_contract', 'pending', 'closed', 'withdrawn', 'expired', 'canceled'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:5', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'description_es' => ['nullable', 'string', 'max:10000'],
            'property_type_id' => ['nullable', 'integer', Rule::exists('property_types', 'id')],
            'property_subtype' => ['nullable', 'string', 'max:80'],
            'listing_type' => [$required, Rule::enum(ListingType::class)],
            'status' => ['sometimes', Rule::in(self::INTERNAL_STATUSES)],
            'list_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'listing_agent_id' => ['nullable', 'integer'],
            'street_address' => ['nullable', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'size:2', 'alpha'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'address_display' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bathrooms' => ['nullable', 'numeric', 'min:0', 'max:100', 'multiple_of:0.5'],
            'living_area_sqft' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'lot_size_sqft' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'year_built' => ['nullable', 'integer', 'min:1600', 'max:'.(now()->year + 3)],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'garage_spaces' => ['nullable', 'integer', 'min:0', 'max:100'],
            'heating' => ['nullable', 'array', 'max:10'],
            'heating.*' => ['string', 'max:60'],
            'cooling' => ['nullable', 'array', 'max:10'],
            'cooling.*' => ['string', 'max:60'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['string', 'distinct', Rule::in(self::AMENITIES)],
            'hoa_fee_monthly' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'annual_taxes' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }

    public function validatedForModel(): array
    {
        $data = $this->validated();
        if (isset($data['status'])) {
            $data['status'] = ListingStatus::from($data['status']);
        }

        return $data;
    }
}
