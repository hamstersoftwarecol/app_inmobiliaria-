<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Needed for the no-double-booking exclusion constraint (agent_id WITH =).
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        }

        // Weekly windows in the agency's local time (DST-safe: converted per date).
        Schema::create('agent_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 = Monday … 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestampsTz();
            $table->unique(['agent_id', 'weekday', 'start_time']);
        });

        Schema::create('agent_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 200)->nullable();
            $table->timestampsTz();
            $table->index(['agent_id', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name', 200);
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);
            $table->string('status', 20)->default('requested');
            $table->text('notes')->nullable();
            $table->text('agent_notes')->nullable();
            $table->string('status_reason', 500)->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestampTz('reminder_24h_sent_at')->nullable();
            $table->timestampTz('reminder_1h_sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['agent_id', 'starts_at']);
            $table->index(['requested_by', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at')->index();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('mail')->default(true);
            $table->timestampTz('consented_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->primary(['user_id', 'event']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE agent_availabilities ADD CONSTRAINT agent_availabilities_range CHECK (start_time < end_time AND weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE agent_time_off ADD CONSTRAINT agent_time_off_range CHECK (starts_at < ends_at)');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_range CHECK (starts_at < ends_at)');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_rating CHECK (feedback_rating IS NULL OR feedback_rating BETWEEN 1 AND 5)');
            // An agent can never hold two live showings at overlapping times, whatever the code path.
            DB::statement(<<<'SQL'
                ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap
                EXCLUDE USING gist (agent_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
                WHERE (status IN ('requested', 'confirmed'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('agent_time_off');
        Schema::dropIfExists('agent_availabilities');
    }
};
