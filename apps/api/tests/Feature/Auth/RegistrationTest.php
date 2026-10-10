<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'email' => 'Maria.Lopez@Example.com',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
            'accept_terms' => true,
            'device_name' => 'Pixel 9',
        ], $overrides);
    }

    public function test_buyer_can_register_from_mobile_and_receives_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'maria.lopez@example.com')
            ->assertJsonPath('data.roles.0.name', 'buyer')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('token.token_type', 'Bearer')
            ->assertJsonMissingPath('data.password');

        $this->assertMatchesRegularExpression('/^\d+\|hls_/', $response->json('token.access_token'));
        $user = User::where('email', 'maria.lopez@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertTrue(AuditLog::where('event', 'auth.registered')->where('user_id', $user->id)->exists());
    }

    public function test_seller_account_type_is_allowed(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['account_type' => 'seller']))
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'seller');
    }

    public function test_staff_roles_cannot_be_self_assigned(): void
    {
        foreach (['agent', 'broker', 'office_manager', 'super_admin'] as $role) {
            $this->postJson('/api/v1/auth/register', $this->payload(['account_type' => $role, 'email' => "{$role}@example.com"]))
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonStructure(['error' => ['details' => ['fields' => ['account_type']]]]);
        }

        $this->assertSame(0, User::count());
    }

    public function test_weak_passwords_and_duplicates_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'password', 'password_confirmation' => 'password']))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['password']]]]);

        User::factory()->create(['email' => 'taken@example.com']);
        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'TAKEN@example.com']))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['accept_terms' => false]))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['accept_terms']]]]);
    }

    public function test_web_registration_starts_a_cookie_session(): void
    {
        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/register', $this->payload(['device_name' => null]))
            ->assertCreated()
            ->assertJsonMissingPath('token');

        $this->withHeaders($this->spaHeaders())->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'maria.lopez@example.com');
    }
}
