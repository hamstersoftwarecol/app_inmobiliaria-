<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiConventionsTest extends TestCase
{
    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_unknown_routes_use_the_error_envelope(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'request_id']])
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_security_headers_and_request_id(): void
    {
        $response = $this->withHeader('X-Request-Id', 'client-req-12345')->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Request-Id', 'client-req-12345');

        $generated = $this->getJson('/api/v1/health', ['X-Request-Id' => 'bad value!'])->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $generated, 'unsafe ids are replaced by a UUID');
    }

    public function test_cors_allows_only_configured_origin_with_credentials(): void
    {
        $this->options('/api/v1/auth/me', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $origin = $this->options('/api/v1/auth/me', [], [
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'GET',
        ])->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame('https://attacker.example', $origin);
    }

    public function test_spanish_validation_messages_are_supported(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [], ['Accept-Language' => 'es'])->assertStatus(422);
        $this->assertSame('es', app()->getLocale());
    }

    public function test_credential_endpoints_are_rate_limited_per_ip(): void
    {
        config(['app_inmobiliaria.security.auth_requests_per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => "user{$i}@example.com"])->assertStatus(202);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user9@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}
