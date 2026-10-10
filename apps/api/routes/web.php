<?php

use Illuminate\Support\Facades\Route;

// API-only application: the web UI lives in apps/web (Next.js).
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'docs' => 'docs/api/openapi.yaml',
]));
