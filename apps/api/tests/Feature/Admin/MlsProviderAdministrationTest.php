<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\MlsConnection;
use App\Models\MlsProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MlsProviderAdministrationTest extends TestCase
{
    private function createProvider(): MlsProvider
    {
        $this->postJson('/api/v1/admin/mls/providers', [
            'code' => 'njmls',
            'name' => 'New Jersey MLS',
            'driver' => 'njmls',
            'protocol' => 'reso_web_api',
            'display_rule' => ['allow_public_display' => true, 'min_refresh_minutes' => 60],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_authorization')
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.display_rule.allow_ai_processing', false);

        return MlsProvider::where('code', 'njmls')->firstOrFail();
    }

    public function test_only_super_admin_manages_providers_broker_can_view(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $this->getJson('/api/v1/admin/mls/providers')->assertOk();
        $this->postJson('/api/v1/admin/mls/providers', ['code' => 'x', 'name' => 'x', 'driver' => 'njmls', 'protocol' => 'reso_web_api'])
            ->assertForbidden();
    }

    public function test_credentials_are_encrypted_and_never_returned(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $provider = $this->createProvider();

        $response = $this->postJson("/api/v1/admin/mls/providers/{$provider->id}/connections", [
            'name' => 'Sandbox',
            'environment' => 'sandbox',
            'base_url' => 'https://reso.example.test/odata',
            'token_url' => 'https://auth.example.test/token',
            'credentials' => ['client_id' => 'my-client', 'client_secret' => 'super-secret-value'],
        ])->assertCreated();

        $this->assertStringNotContainsString('super-secret-value', $response->getContent());
        $response->assertJsonPath('data.connections.0.credential_keys', ['client_id', 'client_secret']);

        $raw = DB::table('mls_connections')->value('credentials');
        $this->assertStringNotContainsString('super-secret-value', $raw);
        $this->assertSame('super-secret-value', MlsConnection::first()->credentials['client_secret']);

        $audit = AuditLog::where('event', 'mls.connection.created')->firstOrFail();
        $this->assertStringNotContainsString('super-secret-value', json_encode($audit->toArray()));
    }

    public function test_http_endpoints_are_rejected(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $provider = $this->createProvider();

        $this->postJson("/api/v1/admin/mls/providers/{$provider->id}/connections", [
            'name' => 'Bad', 'environment' => 'sandbox', 'base_url' => 'http://reso.example.test',
            'credentials' => ['access_token' => 'x'],
        ])->assertStatus(422);
    }

    public function test_authorizing_requires_explicit_licence_confirmation(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $provider = $this->createProvider();

        $this->patchJson("/api/v1/admin/mls/providers/{$provider->id}", ['status' => 'authorized'])->assertStatus(422);
        $this->patchJson("/api/v1/admin/mls/providers/{$provider->id}", ['is_enabled' => true, 'licence_confirmed' => true])
            ->assertStatus(409)->assertJsonPath('error.code', 'mls_not_authorized');

        $this->patchJson("/api/v1/admin/mls/providers/{$provider->id}", [
            'status' => 'authorized', 'is_enabled' => true, 'licence_confirmed' => true, 'licence_reference' => 'Agreement #TEST-1',
        ])->assertOk()->assertJsonPath('data.is_enabled', true);

        $this->assertSame('Agreement #TEST-1', AuditLog::where('event', 'mls.provider.updated')->latest('id')->first()->new_values['licence_reference']);
    }

    public function test_connection_test_performs_handshake_only(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $provider = $this->createProvider();
        $connection = new MlsConnection([
            'name' => 'Sandbox', 'environment' => 'sandbox', 'base_url' => 'https://reso.example.test/odata',
            'token_url' => 'https://auth.example.test/token', 'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
        ]);
        $connection->provider()->associate($provider)->save();

        Http::fake(['https://auth.example.test/token' => Http::sequence()
            ->push(['access_token' => 'x', 'expires_in' => 600])
            ->push([], 401)]);
        $this->postJson("/api/v1/admin/mls/connections/{$connection->id}/test")->assertOk()->assertJsonPath('success', true);
        Http::assertSentCount(1);
        $this->assertSame('verified', $connection->fresh()->status);

        $this->postJson("/api/v1/admin/mls/connections/{$connection->id}/test")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'mls_authentication_failed');
        $this->assertSame('error', $connection->fresh()->status);
    }
}
