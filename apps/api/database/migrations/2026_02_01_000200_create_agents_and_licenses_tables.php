<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 160)->unique();
            $table->string('public_email')->nullable();
            $table->string('public_phone', 32)->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_es')->nullable();
            $table->jsonb('languages')->nullable();
            $table->jsonb('specialties')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('status', 20)->default('active')->index();
            // Public directory opt-in; display also requires a verified licence.
            $table->boolean('is_public')->default(false);
            $table->date('joined_on')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('agent_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('license_number', 40);
            $table->char('license_state', 2)->default('NJ');
            $table->string('license_type', 30);
            $table->string('status', 30)->default('pending_verification')->index();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->string('verification_source', 40)->nullable();
            $table->string('verification_reference', 255)->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('status_changed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            // A licence number may be pending or verified for only one agent at a time.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX agent_licenses_active_number_unique
                ON agent_licenses (license_state, upper(license_number))
                WHERE status IN ('pending_verification', 'verified')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_licenses');
        Schema::dropIfExists('agents');
    }
};
