<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Liveness (`/health`: the process answers) and readiness (`/health/ready`: dependencies work).
 * Readiness reports only check names and states — never error messages, hosts or versions.
 */
class HealthController extends Controller
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    private const QUEUES = ['default', 'notifications', 'mls-sync', 'mls-media', 'documents'];

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'version' => 'v1']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1 as ok, postgis_version() as v')[0]->ok === 1),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            }),
            'storage' => $this->check(fn () => is_array(Storage::disk((string) config('app_inmobiliaria.documents.disk', 'local'))->directories('/'))),
        ];

        // Degraded (reported, not fatal): queue backlog and scheduler heartbeat.
        $backlog = null;
        try {
            $backlog = array_sum(array_map(fn (string $q) => Queue::size($q), self::QUEUES));
        } catch (Throwable) {
            $checks['queue'] = 'fail';
        }
        $checks['queue'] ??= $backlog !== null && $backlog > (int) config('app_inmobiliaria.health.max_queue_backlog', 1000) ? 'degraded' : 'ok';

        try {
            $beat = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable) {
            $beat = null;
        }
        $age = is_int($beat) ? now()->getTimestamp() - $beat : null;
        $checks['scheduler'] = $age !== null && $age <= 180 ? 'ok' : 'degraded';

        $ready = $checks['database'] === 'ok' && $checks['cache'] === 'ok' && $checks['storage'] === 'ok';

        return response()->json([
            'status' => $ready ? (in_array('degraded', $checks, true) || $checks['queue'] === 'fail' ? 'degraded' : 'ok') : 'fail',
            'checks' => $checks,
            'queue_backlog' => $backlog,
            'scheduler_heartbeat_age_seconds' => $age,
        ], $ready ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            return $probe() ? 'ok' : 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
