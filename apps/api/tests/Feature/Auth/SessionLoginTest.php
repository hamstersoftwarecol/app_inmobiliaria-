<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\RoleName;
use Tests\TestCase;

class SessionLoginTest extends TestCase
{
    public function test_web_login_establishes_a_cookie_session(): void
    {
        $this->userWithRole(RoleName::Buyer, ['email' => 'buyer@example.com']);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['email' => 'buyer@example.com', 'password' => 'Password!2345'])
            ->assertOk()
            ->assertJsonPath('mfa_required', false)
            ->assertJsonPath('data.email', 'buyer@example.com')
            ->assertCookie(config('session.cookie'));

        $this->withHeaders($this->spaHeaders())->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_mfa_users_must_complete_the_challenge(): void
    {
        $this->userWithRole(RoleName::OfficeManager, ['email' => 'manager@example.com']);

        $login = $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['email' => 'manager@example.com', 'password' => 'Password!2345'])
            ->assertOk()
            ->assertJsonPath('mfa_required', true)
            ->assertJsonMissingPath('data');

        $this->assertGuest('web');
        $cookie = $login->getCookie(config('session.cookie'))->getValue();

        $this->withHeaders($this->spaHeaders())->withCookie(config('session.cookie'), $cookie)
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_mfa_code');

        $this->withHeaders($this->spaHeaders())->withCookie(config('session.cookie'), $cookie)
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => $this->totp()])
            ->assertOk()
            ->assertJsonPath('data.email', 'manager@example.com');

        $this->assertAuthenticated('web');
    }

    public function test_challenge_without_a_pending_login_fails(): void
    {
        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'mfa_challenge_expired');
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
