<?php

use App\Http\Controllers\Api\V1\Admin\AgencyController;
use App\Http\Controllers\Api\V1\Admin\AgentController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\LicenseController;
use App\Http\Controllers\Api\V1\Admin\MlsProviderController;
use App\Http\Controllers\Api\V1\Admin\OfficeController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Me\AgentProfileController;
use App\Http\Controllers\Api\V1\Public\AgentDirectoryController;
use Illuminate\Support\Facades\Route;

/*
| Hitlease Real Estate REST API — v1. Contract: docs/api/openapi.yaml
*/
Route::prefix('v1')->middleware('throttle:api')->group(function () {

    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => 'v1']))->name('health');

    // ---------------------------------------------------------------- Public directory
    Route::get('/agents', [AgentDirectoryController::class, 'index'])->name('agents.index');
    Route::get('/agents/{slug}', [AgentDirectoryController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')->name('agents.show');

    // ---------------------------------------------------------------- Self-service
    Route::prefix('me')->name('me.')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/agent-profile', [AgentProfileController::class, 'show'])->name('agent-profile.show');
        Route::patch('/agent-profile', [AgentProfileController::class, 'update'])->name('agent-profile.update');
        Route::post('/agent-profile/licenses', [AgentProfileController::class, 'storeLicense'])->name('agent-profile.licenses.store');
    });

    // ---------------------------------------------------------------- Auth (guest)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/mfa/challenge', [AuthController::class, 'mfaChallenge'])->name('mfa.challenge');
            Route::post('/token', [AuthController::class, 'token'])->name('token');
            Route::post('/forgot-password', [PasswordController::class, 'forgot'])->name('password.forgot');
            Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.reset');
        });

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->whereNumber('id')
            ->name('verification.verify');

        // ------------------------------------------------------------ Auth (signed in)
        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1')->name('verification.send');

            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:auth')->name('password.update');
            Route::post('/deactivate', [ProfileController::class, 'deactivate'])->middleware('throttle:auth')->name('deactivate');

            Route::middleware('throttle:auth')->group(function () {
                Route::post('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
                Route::post('/mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');
                Route::post('/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery-codes');
                Route::post('/mfa/disable', [MfaController::class, 'disable'])->name('mfa.disable');
            });

            Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('/sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
            Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
            Route::get('/security-events', [SessionController::class, 'securityEvents'])->name('security-events');
        });
    });

    // ---------------------------------------------------------------- Administration
    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'active', 'mfa.staff'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

        Route::get('/roles', [RoleController::class, 'index'])->can('roles.view')->name('roles.index');
        Route::get('/permissions', [RoleController::class, 'permissions'])->can('roles.view')->name('permissions.index');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->can('roles.manage')->name('roles.permissions');

        Route::get('/agencies', [AgencyController::class, 'index'])->can('agents.view_any')->name('agencies.index');
        Route::post('/agencies', [AgencyController::class, 'store'])->can('agency.manage')->name('agencies.store');
        Route::get('/agencies/{agency}', [AgencyController::class, 'show'])->can('agents.view_any')->name('agencies.show');
        Route::patch('/agencies/{agency}', [AgencyController::class, 'update'])->can('agency.manage')->name('agencies.update');

        Route::get('/offices', [OfficeController::class, 'index'])->can('agents.view_any')->name('offices.index');
        Route::post('/offices', [OfficeController::class, 'store'])->can('agency.manage')->name('offices.store');
        Route::patch('/offices/{office}', [OfficeController::class, 'update'])->can('agency.manage')->name('offices.update');
        Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])->can('agency.manage')->name('offices.destroy');

        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('/agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
        Route::patch('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::post('/agents/{agent}/licenses', [AgentController::class, 'storeLicense'])->name('agents.licenses.store');

        Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses/{license}/verify', [LicenseController::class, 'verify'])->name('licenses.verify');
        Route::post('/licenses/{license}/reject', [LicenseController::class, 'reject'])->name('licenses.reject');
        Route::post('/licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->can('audit_logs.view')->name('audit-logs.index');

        Route::prefix('mls')->name('mls.')->group(function () {
            Route::get('/providers', [MlsProviderController::class, 'index'])->can('mls.view')->name('providers.index');
            Route::post('/providers', [MlsProviderController::class, 'store'])->can('mls.manage')->name('providers.store');
            Route::get('/providers/{provider}', [MlsProviderController::class, 'show'])->can('mls.view')->name('providers.show');
            Route::patch('/providers/{provider}', [MlsProviderController::class, 'update'])->can('mls.manage')->name('providers.update');
            Route::post('/providers/{provider}/connections', [MlsProviderController::class, 'storeConnection'])->can('mls.manage')->name('connections.store');
            Route::post('/connections/{connection}/test', [MlsProviderController::class, 'testConnection'])->can('mls.manage')->name('connections.test');
        });
    });
});
