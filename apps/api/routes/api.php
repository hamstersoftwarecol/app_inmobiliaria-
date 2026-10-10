<?php

use App\Http\Controllers\Api\V1\Admin\AgencyController;
use App\Http\Controllers\Api\V1\Admin\AgentController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\LicenseController;
use App\Http\Controllers\Api\V1\Admin\MlsProviderController;
use App\Http\Controllers\Api\V1\Admin\MlsSyncController;
use App\Http\Controllers\Api\V1\Admin\OfficeController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Crm\LeadActivityController;
use App\Http\Controllers\Api\V1\Crm\LeadController;
use App\Http\Controllers\Api\V1\Crm\ReportController;
use App\Http\Controllers\Api\V1\Crm\TaskController;
use App\Http\Controllers\Api\V1\Manage\PropertyController as ManagePropertyController;
use App\Http\Controllers\Api\V1\Me\AgentProfileController;
use App\Http\Controllers\Api\V1\Me\FavoriteController;
use App\Http\Controllers\Api\V1\Me\NotificationController;
use App\Http\Controllers\Api\V1\Me\SavedSearchController;
use App\Http\Controllers\Api\V1\Public\AgentDirectoryController;
use App\Http\Controllers\Api\V1\Public\InquiryController;
use App\Http\Controllers\Api\V1\Public\PropertyController;
use App\Http\Controllers\Api\V1\Showings\AppointmentController;
use App\Http\Controllers\Api\V1\Showings\AvailabilityController;
use App\Http\Controllers\Api\V1\Showings\ShowingSlotController;
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

    Route::get('/property-types', [PropertyController::class, 'types'])->name('property-types.index');
    Route::middleware('throttle:search')->group(function () {
        Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('/locations', [PropertyController::class, 'locations'])->name('locations.index');
    });
    Route::get('/properties/{slug}', [PropertyController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')->name('properties.show');

    Route::get('/properties/{slug}/showing-slots', [ShowingSlotController::class, 'index'])
        ->where('slug', '[a-z0-9-]+')->middleware('throttle:search')->name('properties.showing-slots');

    // ---------------------------------------------------------------- Showings
    Route::prefix('appointments')->name('appointments.')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');
        Route::post('/', [AppointmentController::class, 'store'])->middleware(['idempotent', 'throttle:bookings'])->name('store');
        Route::post('/book', [AppointmentController::class, 'book'])->middleware(['idempotent', 'throttle:bookings'])->name('book');
        Route::get('/{appointment}', [AppointmentController::class, 'show'])->whereNumber('appointment')->name('show');
        foreach (['confirm', 'decline', 'cancel', 'reschedule', 'complete'] as $action) {
            Route::post("/{appointment}/{$action}", [AppointmentController::class, $action])->whereNumber('appointment')->name($action);
        }
    });

    Route::post('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe'])
        ->middleware('throttle:inquiries')->name('notifications.unsubscribe');

    Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:inquiries')->name('inquiries.store');

    // ---------------------------------------------------------------- CRM (staff & agents; staff need MFA)
    Route::prefix('crm')->name('crm.')->middleware(['auth:sanctum', 'active', 'mfa.staff'])->group(function () {
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/board', [LeadController::class, 'board'])->name('leads.board');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('leads.show');
        Route::patch('/leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead')->name('leads.update');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->whereNumber('lead')->name('leads.destroy');
        Route::post('/leads/{lead}/status', [LeadController::class, 'status'])->whereNumber('lead')->name('leads.status');
        Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->whereNumber('lead')->name('leads.assign');
        Route::post('/leads/{lead}/merge', [LeadController::class, 'merge'])->whereNumber('lead')->name('leads.merge');
        Route::post('/leads/{lead}/activities', [LeadActivityController::class, 'store'])->whereNumber('lead')->name('leads.activities.store');

        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');

        Route::get('/reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
    });

    // ---------------------------------------------------------------- Listing management (staff & agents)
    Route::prefix('manage')->name('manage.')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/properties', [ManagePropertyController::class, 'index'])->name('properties.index');
        Route::post('/properties', [ManagePropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{property}', [ManagePropertyController::class, 'show'])->whereNumber('property')->name('properties.show');
        Route::patch('/properties/{property}', [ManagePropertyController::class, 'update'])->whereNumber('property')->name('properties.update');
        Route::delete('/properties/{property}', [ManagePropertyController::class, 'destroy'])->whereNumber('property')->name('properties.destroy');
        Route::post('/properties/{property}/publish', [ManagePropertyController::class, 'publish'])->whereNumber('property')->name('properties.publish');
        Route::post('/properties/{property}/unpublish', [ManagePropertyController::class, 'unpublish'])->whereNumber('property')->name('properties.unpublish');
        Route::post('/properties/{property}/media', [ManagePropertyController::class, 'storeMedia'])->whereNumber('property')
            ->middleware('throttle:30,1')->name('properties.media.store');
        Route::put('/properties/{property}/media/order', [ManagePropertyController::class, 'reorderMedia'])->whereNumber('property')->name('properties.media.order');
        Route::delete('/properties/{property}/media/{media}', [ManagePropertyController::class, 'destroyMedia'])->whereNumber(['property', 'media'])->name('properties.media.destroy');
    });

    // ---------------------------------------------------------------- Self-service
    Route::prefix('me')->name('me.')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/agent-profile', [AgentProfileController::class, 'show'])->name('agent-profile.show');
        Route::patch('/agent-profile', [AgentProfileController::class, 'update'])->name('agent-profile.update');
        Route::post('/agent-profile/licenses', [AgentProfileController::class, 'storeLicense'])->name('agent-profile.licenses.store');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('notifications.read');
        Route::get('/notification-preferences', [NotificationController::class, 'preferences'])->name('notification-preferences.show');
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');

        Route::get('/availability', [AvailabilityController::class, 'show'])->name('availability.show');
        Route::put('/availability', [AvailabilityController::class, 'update'])->name('availability.update');
        Route::post('/time-off', [AvailabilityController::class, 'storeTimeOff'])->name('time-off.store');
        Route::delete('/time-off/{timeOff}', [AvailabilityController::class, 'destroyTimeOff'])->whereNumber('timeOff')->name('time-off.destroy');

        Route::middleware('can:favorites.manage')->group(function () {
            Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
            Route::get('/favorites/slugs', [FavoriteController::class, 'slugs'])->name('favorites.slugs');
            Route::put('/favorites/{slug}', [FavoriteController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('favorites.store');
            Route::delete('/favorites/{slug}', [FavoriteController::class, 'destroy'])->where('slug', '[a-z0-9-]+')->name('favorites.destroy');
        });

        Route::middleware('can:saved_searches.manage')->group(function () {
            Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
            Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
            Route::patch('/saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])->whereNumber('savedSearch')->name('saved-searches.update');
            Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->whereNumber('savedSearch')->name('saved-searches.destroy');
        });
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

            Route::post('/sync', [MlsSyncController::class, 'sync'])->can('mls.sync')->name('sync');
            Route::get('/sync-jobs', [MlsSyncController::class, 'jobs'])->can('mls.view')->name('sync-jobs.index');
            Route::get('/sync-jobs/{job}', [MlsSyncController::class, 'job'])->can('mls.view')->name('sync-jobs.show');
            Route::get('/status', [MlsSyncController::class, 'status'])->can('mls.view')->name('status');
            Route::get('/providers/{provider}/field-mappings', [MlsSyncController::class, 'fieldMappings'])->can('mls.view')->name('field-mappings.index');
            Route::put('/providers/{provider}/field-mappings', [MlsSyncController::class, 'updateFieldMappings'])->can('mls.manage')->name('field-mappings.update');
        });
    });
});
