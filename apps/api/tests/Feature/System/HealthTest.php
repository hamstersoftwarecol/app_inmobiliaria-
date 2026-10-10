<?php

namespace Tests\Feature\System;

use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_is_public_and_minimal(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }

    public function test_readiness_checks_dependencies_and_the_scheduler_heartbeat(): void
    {
        $this->getJson('/api/v1/health/ready')->assertOk()
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.storage', 'ok')
            ->assertJsonPath('checks.scheduler', 'degraded')
            ->assertJsonPath('status', 'degraded');

        Cache::put(HealthController::HEARTBEAT_KEY, now()->getTimestamp() - 30, 600);
        $this->getJson('/api/v1/health/ready')->assertOk()->assertJsonPath('checks.scheduler', 'ok')->assertJsonPath('status', 'ok')
            ->assertJsonPath('scheduler_heartbeat_age_seconds', 30);

        // A stale heartbeat (scheduler stopped) degrades readiness again.
        Cache::put(HealthController::HEARTBEAT_KEY, now()->getTimestamp() - 600, 600);
        $this->getJson('/api/v1/health/ready')->assertJsonPath('checks.scheduler', 'degraded');
    }

    public function test_readiness_fails_without_the_cache_and_does_not_leak_details(): void
    {
        config(['cache.default' => 'broken', 'cache.stores.broken' => ['driver' => 'redis', 'connection' => 'missing']]);
        $response = $this->getJson('/api/v1/health/ready')->assertStatus(503)->assertJsonPath('status', 'fail')->assertJsonPath('checks.cache', 'fail');
        $this->assertStringNotContainsString('missing', $response->getContent());
    }

    public function test_the_scheduler_writes_its_heartbeat(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('scheduler:heartbeat')->assertSuccessful();
    }
}
