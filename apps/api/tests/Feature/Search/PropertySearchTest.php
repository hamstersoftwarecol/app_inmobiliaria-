<?php

namespace Tests\Feature\Search;

use App\Models\Property;
use App\Models\PropertyType;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    /** Hoboken, Jersey City and Newark (fictitious listings, real coordinates). */
    private function seedListings(): array
    {
        $condo = PropertyType::query()->where('code', 'condominium')->value('id');

        return [
            'hoboken' => Property::factory()->published()->create([
                'title' => 'Hoboken brownstone', 'list_price' => 900000, 'bedrooms' => 4, 'bathrooms' => 3, 'living_area_sqft' => 2400,
                'year_built' => 1905, 'amenities' => ['fireplace', 'garage'], 'latitude' => 40.7440, 'longitude' => -74.0324,
            ]),
            'jersey_city' => Property::factory()->published()->create([
                'title' => 'Jersey City condo', 'city' => 'Jersey City', 'postal_code' => '07302', 'list_price' => 520000,
                'bedrooms' => 2, 'bathrooms' => 2, 'living_area_sqft' => 1100, 'year_built' => 2015, 'amenities' => ['gym', 'elevator'],
                'property_type_id' => $condo, 'latitude' => 40.7178, 'longitude' => -74.0431,
            ]),
            'newark' => Property::factory()->published()->create([
                'title' => 'Newark rental', 'city' => 'Newark', 'county' => 'Essex', 'postal_code' => '07102', 'listing_type' => 'rent',
                'list_price' => 2400, 'bedrooms' => 1, 'bathrooms' => 1, 'living_area_sqft' => 700, 'latitude' => 40.7357, 'longitude' => -74.1724,
            ]),
        ];
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        return collect($this->getJson('/api/v1/properties?'.$query)->assertOk()->json('data'))->pluck('title')->all();
    }

    public function test_attribute_filters_combine(): void
    {
        $this->seedListings();

        $this->assertSame(['Newark rental'], $this->titles('listing_type=rent'));
        $this->assertSame(['Jersey City condo'], $this->titles('property_type=condominium'));
        $this->assertSame(['Newark rental'], $this->titles('county=essex'));
        $this->assertSame(['Hoboken brownstone'], $this->titles('min_baths=2.5&min_sqft=2000'));
        $this->assertSame(['Jersey City condo'], $this->titles('min_year=2000&listing_type=sale'));
        $this->assertSame(['Hoboken brownstone'], $this->titles('amenities[]=garage&amenities[]=fireplace'));
        $this->assertSame([], $this->titles('amenities[]=garage&amenities[]=gym'));
        $this->assertSame(['Jersey City condo', 'Hoboken brownstone'], $this->titles('listing_type=sale&sort=price_asc'));
        $this->assertSame(['Hoboken brownstone', 'Jersey City condo', 'Newark rental'], $this->titles('sort=sqft_desc'));
    }

    public function test_bounding_box_radius_and_polygon(): void
    {
        $this->seedListings();

        // Hudson waterfront box: Hoboken + Jersey City, not Newark.
        $this->assertEqualsCanonicalizing(['Hoboken brownstone', 'Jersey City condo'], $this->titles('bbox=-74.06,40.70,-74.00,40.76'));

        // 2.5 miles around Hoboken reaches Jersey City (~1.9 mi), not Newark (~7 mi); nearest first.
        $this->assertSame(['Hoboken brownstone', 'Jersey City condo'], $this->titles('lat=40.744&lng=-74.0324&radius_miles=2.5&sort=distance'));
        $this->assertSame(['Jersey City condo', 'Hoboken brownstone'], $this->titles('lat=40.7178&lng=-74.0431&radius_miles=2.5&sort=distance'));

        // Triangle around Newark only.
        $this->assertSame(['Newark rental'], $this->titles('polygon='.urlencode('-74.20 40.70,-74.14 40.70,-74.17 40.77')));
    }

    public function test_geographic_filters_skip_listings_with_hidden_addresses(): void
    {
        $listings = $this->seedListings();
        $listings['hoboken']->update(['address_display' => false]);

        $this->assertSame(['Jersey City condo'], $this->titles('bbox=-74.06,40.70,-74.00,40.76'));
        // Still found by city, without coordinates.
        $this->getJson('/api/v1/properties?city=hoboken')->assertOk()
            ->assertJsonPath('data.0.title', 'Hoboken brownstone')->assertJsonPath('data.0.location', null);
    }

    public function test_invalid_geographic_input_is_rejected(): void
    {
        foreach ([
            'bbox=1,2,3' => 'bbox',
            'bbox=-74,40.8,-74.1,40.7' => 'bbox',
            'lat=40.7&lng=-74&radius_miles=500' => 'radius_miles',
            'radius_miles=2' => 'lat',
            'polygon='.urlencode('-74 40,-74.1 40') => 'polygon',
            'polygon='.urlencode('-74 40,-74.1 40,x y') => 'polygon',
            'bbox=-74.06,40.70,-74.00,40.76&polygon='.urlencode('-74 40,-74.1 40,-74 41') => 'bbox',
            'amenities[]=helipad' => 'amenities.0',
        ] as $query => $field) {
            $this->getJson('/api/v1/properties?'.$query)->assertUnprocessable()->assertJsonValidationErrors([$field], 'error.details.fields');
        }
    }

    public function test_location_autocomplete_lists_cities_and_zip_codes_with_public_listings(): void
    {
        $this->seedListings();
        Property::factory()->create(['city' => 'Hobart', 'postal_code' => '07099']); // draft: never suggested

        $this->getJson('/api/v1/locations?q=hob')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'city')
            ->assertJsonPath('data.0.label', 'Hoboken, NJ')
            ->assertJsonPath('data.0.listings', 1)
            ->assertJsonPath('data.0.center.latitude', 40.744);

        $this->getJson('/api/v1/locations?q=0730')->assertOk()
            ->assertJsonPath('data.0.type', 'postal_code')
            ->assertJsonPath('data.0.label', '07302 · Jersey City, NJ');

        $this->getJson('/api/v1/locations?q=h')->assertUnprocessable();
    }

    public function test_ssr_requests_are_rate_limited_per_forwarded_visitor_only_with_the_shared_key(): void
    {
        config(['hitlease.security.ssr_key' => 'test-ssr-key']);
        RateLimiter::clear('search:ssr:203.0.113.7');

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/properties', ['X-Hitlease-SSR-Key' => 'test-ssr-key', 'X-Hitlease-Client-IP' => '203.0.113.7'])->assertOk();
        }
        // That visitor is throttled; another visitor behind the same web server is not.
        $this->getJson('/api/v1/properties', ['X-Hitlease-SSR-Key' => 'test-ssr-key', 'X-Hitlease-Client-IP' => '203.0.113.7'])->assertStatus(429);
        $this->getJson('/api/v1/properties', ['X-Hitlease-SSR-Key' => 'test-ssr-key', 'X-Hitlease-Client-IP' => '203.0.113.8'])->assertOk();

        // Without the key (or with a wrong one) the forwarded IP is ignored: the connection IP is used.
        $spoofed = Request::create('/api/v1/properties', 'GET', server: ['REMOTE_ADDR' => '198.51.100.1']);
        $spoofed->headers->set('X-Hitlease-Client-IP', '203.0.113.9');
        $this->assertSame('198.51.100.1', AppServiceProvider::clientKey($spoofed));
        $spoofed->headers->set('X-Hitlease-SSR-Key', 'wrong-key');
        $this->assertSame('198.51.100.1', AppServiceProvider::clientKey($spoofed));
    }
}
