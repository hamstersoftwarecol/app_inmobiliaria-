<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ProfileAndMfaTest extends TestCase
{
    public function test_user_can_update_profile_and_email_change_requires_password(): void
    {
        $user = $this->actingAsRole(RoleName::Buyer);

        $this->patchJson('/api/v1/auth/profile', ['first_name' => 'Ana', 'locale' => 'es', 'timezone' => 'America/Chicago'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ana')
            ->assertJsonPath('data.locale', 'es');

        $this->patchJson('/api/v1/auth/profile', ['email' => 'new@example.com'])->assertStatus(422);

        $this->patchJson('/api/v1/auth/profile', ['email' => 'new@example.com', 'current_password' => 'Password!2345'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);

        $this->patchJson('/api/v1/auth/profile', ['timezone' => 'Europe/Madrid'])->assertStatus(422);
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_full_mfa_enrolment_lifecycle(): void
    {
        $user = $this->actingAsRole(RoleName::Agent);
        $g = new Google2FA;

        $this->postJson('/api/v1/auth/mfa/setup', ['password' => 'bad'])->assertStatus(422);

        $setup = $this->postJson('/api/v1/auth/mfa/setup', ['password' => 'Password!2345'])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url']);
        $secret = $setup->json('secret');
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('otpauth_url'));

        // Secret is encrypted at rest.
        $raw = DB::table('users')->where('id', $user->id)->value('mfa_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertFalse($user->fresh()->hasMfaEnabled());

        $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '000000'])->assertStatus(422);
        $codes = $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $g->getCurrentOtp($secret)])
            ->assertOk()
            ->assertJsonPath('mfa_enabled', true)
            ->json('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()->hasMfaEnabled());

        // Recovery codes are stored hashed, never in plain text.
        $this->assertStringNotContainsString($codes[0], (string) json_encode($user->fresh()->mfa_recovery_codes));

        $this->postJson('/api/v1/auth/mfa/setup', ['password' => 'Password!2345'])->assertStatus(409);

        // Disabling requires password AND a valid second factor.
        $this->postJson('/api/v1/auth/mfa/disable', ['password' => 'Password!2345'])->assertStatus(422);
        // Next time-step code (the current one was consumed by confirm and cannot be replayed).
        $nextCode = $g->oathTotp($secret, $g->getTimestamp() + 1);
        $this->postJson('/api/v1/auth/mfa/disable', ['password' => 'Password!2345', 'code' => $nextCode])
            ->assertOk()
            ->assertJsonPath('mfa_enabled', false);

        $this->assertSame(
            ['auth.mfa.setup_started', 'auth.mfa.enabled', 'auth.mfa.disabled'],
            AuditLog::where('event', 'like', 'auth.mfa.%')->where('event', '!=', 'auth.mfa.failed')->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_tokens_can_be_listed_and_revoked_but_only_own(): void
    {
        $user = $this->userWithRole(RoleName::Buyer);
        $other = $this->userWithRole(RoleName::Buyer);
        $mine = $user->createToken('phone');
        $theirs = $other->createToken('tablet');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonFragment(['id' => 'token:'.$mine->accessToken->id, 'name' => 'phone'])
            ->assertJsonMissing(['name' => 'tablet']);

        $this->deleteJson('/api/v1/auth/sessions/token:'.$theirs->accessToken->id)->assertNotFound();
        $this->deleteJson('/api/v1/auth/sessions/token:'.$mine->accessToken->id)->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_security_events_only_show_own_activity(): void
    {
        $user = $this->userWithRole(RoleName::Buyer, ['email' => 'me@example.com']);
        $this->userWithRole(RoleName::Buyer, ['email' => 'other@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'me@example.com', 'password' => 'Password!2345', 'device_name' => 'x']);
        $this->postJson('/api/v1/auth/token', ['email' => 'other@example.com', 'password' => 'Password!2345', 'device_name' => 'x']);

        Sanctum::actingAs($user);
        $events = $this->getJson('/api/v1/auth/security-events')->assertOk()->json('data');

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame($user->id, AuditLog::find($event['id'])->user_id);
        }
    }

    public function test_self_deactivation_revokes_access(): void
    {
        $user = $this->userWithRole(RoleName::Buyer);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/deactivate', ['password' => 'Password!2345'])->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertFalse(User::find($user->id)->isActive());
    }
}
