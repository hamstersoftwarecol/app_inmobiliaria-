<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class TokenLoginTest extends TestCase
{
    private function login(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/token', array_merge([
            'email' => 'agent@example.com',
            'password' => 'Password!2345',
            'device_name' => 'iPhone 17',
        ], $overrides));
    }

    public function test_mobile_login_issues_a_token_that_authenticates_requests(): void
    {
        $this->userWithRole(RoleName::Agent, ['email' => 'agent@example.com']);

        $token = $this->login(['email' => 'AGENT@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'agent')
            ->assertJsonPath('token.token_type', 'Bearer')
            ->json('token.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'agent@example.com')
            ->assertJsonFragment(['properties.update_own']);

        $this->assertTrue(AuditLog::where('event', 'auth.login.succeeded')->exists());
        $this->assertNotNull(User::first()->last_login_at);
    }

    public function test_invalid_credentials_return_generic_error(): void
    {
        $this->userWithRole(RoleName::Agent, ['email' => 'agent@example.com']);

        $this->login(['password' => 'wrong-password'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_credentials');
        $this->login(['email' => 'nobody@example.com'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_account_is_locked_after_repeated_failures(): void
    {
        $this->userWithRole(RoleName::Agent, ['email' => 'agent@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->login(['password' => 'wrong-password'])->assertStatus(422);
        }

        $this->login()->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_attempts')
            ->assertHeader('Retry-After');

        $this->assertTrue(AuditLog::where('event', 'auth.login.locked_out')->exists());
    }

    public function test_deactivated_accounts_cannot_log_in(): void
    {
        User::factory()->role(RoleName::Buyer)->deactivated()->create(['email' => 'agent@example.com']);

        $this->login()->assertForbidden()->assertJsonPath('error.code', 'account_deactivated');
    }

    public function test_mfa_code_is_required_and_verified_for_enrolled_users(): void
    {
        $this->userWithRole(RoleName::Broker, ['email' => 'agent@example.com']);

        $this->login()->assertStatus(422)->assertJsonPath('error.code', 'mfa_required');
        $this->login(['mfa_code' => '000000'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_mfa_code');

        $code = $this->totp();
        $this->login(['mfa_code' => $code])->assertCreated();

        // The same TOTP code cannot be replayed.
        $this->login(['mfa_code' => $code])->assertStatus(422)->assertJsonPath('error.code', 'invalid_mfa_code');
    }

    public function test_recovery_codes_are_single_use(): void
    {
        $this->userWithRole(RoleName::Broker, ['email' => 'agent@example.com']);

        $this->login(['recovery_code' => 'RECOV-ERY01'])->assertCreated();
        $this->login(['recovery_code' => 'recov-ery01'])->assertStatus(422)->assertJsonPath('error.code', 'invalid_mfa_code');
    }

    public function test_mfa_failures_lock_the_second_factor(): void
    {
        $this->userWithRole(RoleName::Broker, ['email' => 'agent@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->login(['mfa_code' => '000000'])->assertStatus(422);
        }

        $this->login(['mfa_code' => $this->totp()])->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->userWithRole(RoleName::Buyer, ['email' => 'agent@example.com']);
        $first = $this->login()->json('token.access_token');
        $second = $this->login(['device_name' => 'iPad'])->json('token.access_token');

        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_cookie_login_endpoint_rejects_non_browser_clients(): void
    {
        $this->userWithRole(RoleName::Buyer, ['email' => 'agent@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'agent@example.com', 'password' => 'Password!2345'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'stateful_session_required');
    }
}
