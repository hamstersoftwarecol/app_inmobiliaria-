<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation of the MLS Integration Engine: provider catalogue, encrypted
 * connections and per-provider display/compliance rules. Listings, sync jobs
 * and sync logs arrive with Phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mls_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            // Adapter key resolved by MLSConnectionManager (e.g. reso_web_api, njmls).
            $table->string('driver', 50);
            $table->string('protocol', 30)->default('reso_web_api');
            $table->string('status', 30)->default('pending_authorization')->index();
            // Never true until a data licence is signed and official specs are verified.
            $table->boolean('is_enabled')->default(false);
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('mls_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('environment', 20)->default('sandbox');
            $table->string('base_url')->nullable();
            $table->string('token_url')->nullable();
            // JSON credentials encrypted with APP_KEY; never serialized to clients.
            $table->text('credentials')->nullable();
            $table->string('status', 30)->default('inactive')->index();
            $table->timestampTz('last_authenticated_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->string('last_cursor')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['mls_provider_id', 'environment', 'name']);
        });

        Schema::create('mls_display_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('attribution_template')->nullable();
            $table->text('disclaimer')->nullable();
            $table->unsignedInteger('min_refresh_minutes')->default(720);
            $table->unsignedInteger('retention_days')->nullable();
            $table->jsonb('allowed_fields')->nullable();
            $table->jsonb('photo_rules')->nullable();
            // Every permission defaults to "deny" until the licence says otherwise.
            $table->boolean('allow_public_display')->default(false);
            $table->boolean('allow_mobile_display')->default(false);
            $table->boolean('allow_ai_processing')->default(false);
            $table->boolean('hide_withdrawn_immediately')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_display_rules');
        Schema::dropIfExists('mls_connections');
        Schema::dropIfExists('mls_providers');
    }
};
