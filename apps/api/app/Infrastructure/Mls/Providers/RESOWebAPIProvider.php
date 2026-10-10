<?php

namespace App\Infrastructure\Mls\Providers;

use App\Application\Mls\MLSNormalizationService;
use App\Domain\Mls\Contracts\MLSProviderInterface;
use App\Domain\Mls\DTOs\MLSChangeBatch;
use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\DTOs\ProviderCapabilities;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Generic RESO Web API (OData v4) adapter following the RESO Web API Core
 * specification: Property and Media resources, $filter/$orderby/$top,
 * server-driven paging via @odata.nextLink, and OAuth2 client-credentials or
 * static bearer tokens.
 *
 * Endpoint base URL, token URL and credentials come exclusively from the
 * stored MlsConnection — nothing is hard-coded.
 */
class RESOWebAPIProvider implements MLSProviderInterface
{
    protected const CODE = 'reso';

    /** Whether the provider's official specification has been reviewed against this adapter. */
    protected const SPECIFICATION_VERIFIED = false;

    public function __construct(
        protected readonly MlsConnection $connection,
        protected readonly MLSNormalizationService $normalizer,
    ) {}

    public function code(): string
    {
        return $this->connection->provider?->code ?? static::CODE;
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            protocol: 'reso_web_api',
            supportsMetadata: true,
            supportsMedia: true,
            supportsIncremental: true,
            specificationVerified: static::SPECIFICATION_VERIFIED,
        );
    }

    public function authenticate(): void
    {
        $this->accessToken(forceRefresh: true);
        $this->connection->forceFill(['last_authenticated_at' => now()])->saveQuietly();
    }

    public function fetchMetadata(): ?string
    {
        return $this->get($this->url('$metadata'), [], 'application/xml')->body();
    }

    public function fetchListings(array $filters = []): iterable
    {
        $clauses = [];
        if (! empty($filters['status'])) {
            $statuses = array_map(fn ($s) => "StandardStatus eq '".$this->escape((string) $s)."'", (array) $filters['status']);
            $clauses[] = '('.implode(' or ', $statuses).')';
        }
        if (! empty($filters['modified_since'])) {
            $clauses[] = 'ModificationTimestamp gt '.$this->odataDate((string) $filters['modified_since']);
        }

        $query = array_filter([
            '$filter' => $clauses !== [] ? implode(' and ', $clauses) : null,
            '$orderby' => 'ModificationTimestamp asc,ListingKey asc',
            '$top' => (int) ($filters['page_size'] ?? config('hitlease.mls.default_page_size', 200)),
        ]);

        $url = $this->url('Property');
        do {
            $payload = $this->get($url, $query)->json();
            foreach ($payload['value'] ?? [] as $record) {
                if (($dto = $this->normalize($record)) !== null) {
                    yield $dto;
                }
            }
            $url = $payload['@odata.nextLink'] ?? null;
            $query = [];
            if ($url !== null) {
                $this->assertSameHost($url);
            }
        } while ($url !== null);
    }

    public function fetchListing(string $listingKey): ?MLSListingDTO
    {
        $response = $this->get($this->url("Property('".rawurlencode($this->escape($listingKey))."')"), [], 'application/json', allow404: true);

        return $response->status() === 404 ? null : $this->normalize($response->json());
    }

    public function fetchMedia(string $listingKey): iterable
    {
        $payload = $this->get($this->url('Media'), [
            '$filter' => "ResourceRecordKey eq '".$this->escape($listingKey)."' and ResourceName eq 'Property'",
            '$orderby' => 'Order asc',
        ])->json();

        foreach ($payload['value'] ?? [] as $record) {
            if (($media = $this->normalizer->normalizeMedia($listingKey, $record)) !== null) {
                yield $media;
            }
        }
    }

    /**
     * Cursor format: "<ISO-8601 ModificationTimestamp>|<ListingKey>" so that
     * records sharing a timestamp across page boundaries are never skipped.
     */
    public function fetchChanges(?string $cursor = null): MLSChangeBatch
    {
        $pageSize = (int) config('hitlease.mls.default_page_size', 200);
        $filter = null;

        if ($cursor !== null && $cursor !== '') {
            [$timestamp, $key] = array_pad(explode('|', $cursor, 2), 2, '');
            $ts = $this->odataDate($timestamp);
            $filter = $key !== ''
                ? "(ModificationTimestamp gt {$ts} or (ModificationTimestamp eq {$ts} and ListingKey gt '".$this->escape($key)."'))"
                : "ModificationTimestamp gt {$ts}";
        }

        $payload = $this->get($this->url('Property'), array_filter([
            '$filter' => $filter,
            '$orderby' => 'ModificationTimestamp asc,ListingKey asc',
            '$top' => $pageSize,
        ]))->json();

        $records = $payload['value'] ?? [];
        $listings = [];
        $nextCursor = $cursor;

        foreach ($records as $record) {
            if (isset($record['ModificationTimestamp'], $record['ListingKey'])) {
                $nextCursor = $record['ModificationTimestamp'].'|'.$record['ListingKey'];
            }
            if (($dto = $this->normalize($record)) !== null) {
                $listings[] = $dto;
            }
        }

        return new MLSChangeBatch($listings, $nextCursor, count($records) >= $pageSize || isset($payload['@odata.nextLink']));
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    protected function normalize(?array $record): ?MLSListingDTO
    {
        if ($record === null) {
            return null;
        }

        try {
            return $this->normalizer->normalizeListing(
                $this->code(),
                $record,
                $this->fieldMap(),
                $this->connection->provider?->displayRule?->attribution_template,
            );
        } catch (Throwable $e) {
            Log::warning('MLS record skipped during normalization', [
                'provider' => $this->code(),
                'listing_key' => $record['ListingKey'] ?? null,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @var array<string, string>|null */
    private ?array $fieldMapCache = null;

    /**
     * Provider-specific overrides of the RESO Data Dictionary field names,
     * maintained by super admins in `mls_field_mappings`.
     *
     * @return array<string, string>
     */
    protected function fieldMap(): array
    {
        return $this->fieldMapCache ??= $this->connection->provider
            ? $this->connection->provider->fieldMappings()->pluck('provider_field', 'internal_field')->all()
            : [];
    }

    protected function accessToken(bool $forceRefresh = false): string
    {
        $credentials = $this->connection->credentials ?? [];

        if (! empty($credentials['access_token'])) {
            return (string) $credentials['access_token'];
        }

        foreach (['client_id', 'client_secret'] as $required) {
            if (empty($credentials[$required])) {
                throw MLSException::missingConfiguration("credentials.{$required}");
            }
        }
        if (empty($this->connection->token_url)) {
            throw MLSException::missingConfiguration('token_url');
        }
        $this->assertHttps($this->connection->token_url);

        $cacheKey = "mls:token:{$this->connection->getKey()}";
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('mls.http.timeout', 30))
                ->acceptJson()
                ->post($this->connection->token_url, array_filter([
                    'grant_type' => 'client_credentials',
                    'client_id' => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'scope' => $credentials['scope'] ?? null,
                ]));
        } catch (ConnectionException) {
            throw MLSException::authenticationFailed($this->code(), 0);
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw MLSException::authenticationFailed($this->code(), $response->status());
        }

        $token = $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function get(string $url, array $query = [], string $accept = 'application/json', bool $allow404 = false): Response
    {
        $this->assertHttps($url);
        $this->throttle();

        // An empty query array would make Guzzle drop the query string of
        // server-driven paging links (@odata.nextLink), so omit it entirely.
        $send = fn () => $query === [] ? $this->client($accept)->get($url) : $this->client($accept)->get($url, $query);

        try {
            $response = $send();
        } catch (RequestException $e) {
            $response = $e->response;
        } catch (ConnectionException) {
            throw MLSException::requestFailed($this->code(), 0);
        }

        if ($response->status() === 401) {
            // Token may have been revoked/expired early: refresh once.
            $this->accessToken(forceRefresh: true);
            $response = $send();
        }

        if ($allow404 && $response->status() === 404) {
            return $response;
        }

        if (! $response->successful()) {
            throw MLSException::requestFailed($this->code(), $response->status());
        }

        return $response;
    }

    protected function client(string $accept): PendingRequest
    {
        $base = (int) config('mls.http.retry_base_ms', 500);

        return Http::withToken($this->accessToken())
            ->accept($accept)
            ->timeout((int) config('mls.http.timeout', 30))
            ->retry(
                (int) config('mls.http.retries', 3),
                fn (int $attempt) => $base * (2 ** ($attempt - 1)),
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true)),
                throw: false,
            );
    }

    protected function throttle(): void
    {
        $key = "mls:requests:{$this->connection->getKey()}";
        $max = (int) config('mls.http.max_requests_per_minute', 60);

        if (! RateLimiter::attempt($key, $max, fn () => true, 60)) {
            throw MLSException::rateLimited($this->code(), RateLimiter::availableIn($key));
        }
    }

    protected function url(string $path): string
    {
        $base = rtrim((string) $this->connection->base_url, '/');
        if ($base === '') {
            throw MLSException::missingConfiguration('base_url');
        }

        return $base.'/'.ltrim($path, '/');
    }

    protected function assertHttps(string $url): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw MLSException::insecureUrl($url);
        }
    }

    /**
     * Prevents server-driven paging links from redirecting requests (and the
     * bearer token) to another host.
     */
    protected function assertSameHost(string $url): void
    {
        if (parse_url($url, PHP_URL_HOST) !== parse_url((string) $this->connection->base_url, PHP_URL_HOST)) {
            throw MLSException::insecureUrl($url);
        }
    }

    protected function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    protected function odataDate(string $value): string
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
