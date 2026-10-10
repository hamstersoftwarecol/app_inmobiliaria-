<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('new');
            $table->string('source', 30);
            $table->string('intent', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            // Normalized keys for duplicate detection (lower-case e-mail, last 10 phone digits).
            $table->string('email_key', 255)->nullable()->index();
            $table->string('phone_key', 15)->nullable()->index();
            $table->text('message')->nullable();
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->jsonb('preferences')->nullable();
            $table->string('preferred_language', 2)->nullable();
            $table->boolean('contact_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->timestampTz('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('first_contacted_at')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['status', 'assigned_agent_id']);
            $table->index('created_at');
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['lead_id', 'occurred_at']);
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->timestampTz('due_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['assigned_to', 'completed_at', 'due_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leads ADD CONSTRAINT leads_budget_range CHECK (budget_min IS NULL OR budget_max IS NULL OR budget_min <= budget_max)');
            DB::statement('CREATE INDEX crm_tasks_reminders_idx ON crm_tasks (due_at) WHERE completed_at IS NULL AND reminder_sent_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
