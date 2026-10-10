<?php

namespace Tests\Unit\Mls;

use App\Application\Mls\MLSNormalizationService;
use PHPUnit\Framework\TestCase;

class MLSNormalizationServiceTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function fixtures(string $file): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../Fixtures/mls/'.$file), true)['value'];
    }

    public function test_it_normalizes_a_complete_reso_record(): void
    {
        $dto = (new MLSNormalizationService)->normalizeListing('test', $this->fixtures('property.json')[0]);

        $this->assertSame('test:TEST-KEY-0001', $dto->compositeKey());
        $this->assertSame('TEST-0001', $dto->listingId);
        $this->assertSame('active', $dto->status);
        $this->assertSame(689000.0, $dto->listPrice);
        $this->assertSame('USD', $dto->currency);
        $this->assertSame('100 Example Avenue', $dto->streetAddress);
        $this->assertSame('Bergen', $dto->county);
        $this->assertSame('NJ', $dto->state);
        $this->assertSame('07601', $dto->postalCode);
        $this->assertSame(2.5, $dto->bathrooms);
        $this->assertSame(10890.0, $dto->lotSizeSqft);
        $this->assertSame(['Forced Air', 'Natural Gas'], $dto->heating);
        $this->assertSame(['Central Air'], $dto->cooling);
        $this->assertSame(100.0, $dto->hoaFee, 'annual HOA fee converted to monthly');
        $this->assertSame('Listing courtesy of Test Realty Office', $dto->attribution);
        $this->assertTrue($dto->internetDisplayAllowed);
        $this->assertSame('2026-03-01T10:00:00+00:00', $dto->modifiedAt?->format(DATE_ATOM));
        $this->assertSame('TEST-KEY-0001', $dto->raw['ListingKey'], 'original payload is preserved');
    }

    public function test_it_handles_alternative_representations(): void
    {
        $dto = (new MLSNormalizationService)->normalizeListing('test', $this->fixtures('property.json')[1], attribution: 'Courtesy of {broker} / {agent}');

        $this->assertSame('withdrawn', $dto->status);
        $this->assertSame('200 Sample Street Unit 4B', $dto->streetAddress);
        $this->assertSame(1076.39, $dto->livingAreaSqft, 'square meters converted');
        $this->assertSame(2.0, $dto->bathrooms);
        $this->assertFalse($dto->internetDisplayAllowed);
        $this->assertSame('Courtesy of Test Brokerage Two / Test Agent Two', $dto->attribution);
    }

    public function test_it_discards_invalid_values(): void
    {
        $dto = (new MLSNormalizationService)->normalizeListing('test', $this->fixtures('property.json')[2]);

        $this->assertSame('active_under_contract', $dto->status);
        $this->assertSame(3200.0, $dto->listPrice);
        $this->assertNull($dto->latitude, '0,0 coordinates are treated as missing');
        $this->assertNull($dto->yearBuilt, 'implausible year rejected');
        $this->assertNull($dto->attribution);
    }

    public function test_provider_field_map_overrides_defaults(): void
    {
        $dto = (new MLSNormalizationService)->normalizeListing('test', ['LN' => 'X1', 'LP' => '5'], ['listing_key' => 'LN', 'list_price' => 'LP']);

        $this->assertSame('X1', $dto->listingKey);
        $this->assertSame(5.0, $dto->listPrice);
    }

    public function test_records_without_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MLSNormalizationService)->normalizeListing('test', ['ListPrice' => 1]);
    }

    public function test_media_requires_https_and_respects_permissions(): void
    {
        $service = new MLSNormalizationService;
        [$ok, $insecure, $private] = $this->fixtures('media.json');

        $this->assertSame('https://media.example.test/1.jpg', $service->normalizeMedia('TEST-KEY-0001', $ok)?->url);
        $this->assertNull($service->normalizeMedia('TEST-KEY-0001', $insecure));
        $this->assertFalse($service->normalizeMedia('TEST-KEY-0001', $private)?->displayAllowed);
    }
}
