<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\RoleName;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordAndVerificationTest extends TestCase
{
    public function test_forgot_password_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = $this->userWithRole(RoleName::Buyer, ['email' => 'buyer@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'Buyer@Example.com'])->assertStatus(202);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])->assertStatus(202);

        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user) {
            return str_starts_with($n->resetUrl($user), 'http://localhost:3000/reset-password?token=');
        });
    }

    public function test_password_reset_changes_password_and_revokes_tokens(): void
    {
        $user = $this->userWithRole(RoleName::Buyer, ['email' => 'buyer@example.com']);
        $user->createToken('old-device');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'buyer@example.com',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        $this->userWithRole(RoleName::Buyer, ['email' => 'buyer@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid',
            'email' => 'buyer@example.com',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_signed_verification_link_verifies_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->role(RoleName::Buyer)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(202);

        $link = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $n) use ($user, &$link) {
            $link = $n->verificationUrl($user);

            return true;
        });

        $this->assertStringStartsWith('http://localhost:3000/verify-email?', $link);
        parse_str(parse_url($link, PHP_URL_QUERY), $q);

        // Tampered signature is rejected.
        $this->getJson("/api/v1/auth/email/verify/{$q['id']}/{$q['hash']}?expires={$q['expires']}&signature=bad")
            ->assertForbidden();

        $this->getJson("/api/v1/auth/email/verify/{$q['id']}/{$q['hash']}?expires={$q['expires']}&signature={$q['signature']}")
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = $this->actingAsRole(RoleName::Buyer);
        $user->createToken('other-device');

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertStatus(422);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'Password!2345',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }
}
