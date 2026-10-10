<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mls_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_provider_id')->constrained()->cascadeOnDelete();
            $table->string('internal_field', 60);
            $table->string('provider_field', 100);
            $table->timestampsTz();
            $table->unique(['mls_provider_id', 'internal_field']);
        });

        Schema::create('mls_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_connection_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('queued')->index();
            $table->string('cursor_before')->nullable();
            $table->string('cursor_after')->nullable();
            $table->jsonb('stats')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['mls_connection_id', 'created_at']);
        });

        Schema::create('mls_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_sync_job_id')->constrained()->cascadeOnDelete();
            $table->string('level', 10);
            $table->string('message', 500);
            $table->string('listing_key', 100)->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['mls_sync_job_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_sync_logs');
        Schema::dropIfExists('mls_sync_jobs');
        Schema::dropIfExists('mls_field_mappings');
    }
};
