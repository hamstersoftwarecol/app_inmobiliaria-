<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            // Counter-offers form a chain; `parent_id` points at the offer being countered.
            $table->foreignId('parent_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('buyer_agent_external', 200)->nullable();
            $table->string('buyer_name', 200);
            $table->string('side', 10)->default('buyer'); // who made this version: buyer | seller
            $table->string('status', 20)->default('draft');
            $table->decimal('amount', 14, 2);
            $table->decimal('earnest_money', 14, 2)->nullable();
            $table->string('financing', 20);
            $table->decimal('down_payment_percent', 5, 2)->nullable();
            $table->date('closing_date')->nullable();
            $table->jsonb('contingencies')->nullable();
            $table->text('terms')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->string('response_note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['property_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('listing_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('buyer_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open | closed | cancelled
            $table->decimal('sale_price', 14, 2);
            $table->date('contract_date');
            $table->date('closing_date')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'closing_date']);
        });

        Schema::create('transaction_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20); // buyer | seller | attorney | lender | title | other
            $table->string('name', 200);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestampsTz();
            $table->unique(['transaction_id', 'user_id']);
        });

        Schema::create('transaction_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title', 200);
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['transaction_id', 'sort_order']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 30);
            $table->string('visibility', 10)->default('internal'); // internal | shared
            $table->date('retain_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['transaction_id', 'category']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('scan_status', 20)->default('pending'); // pending | clean | infected | unscanned | error
            $table->string('scan_detail', 255)->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20); // link_issued | download | upload | delete
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['document_version_id', 'created_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 200)->nullable();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('joined_at')->useCurrent();
            $table->primary(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['conversation_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_amount_positive CHECK (amount > 0)');
            // One accepted offer and one open transaction per property at a time.
            DB::statement("CREATE UNIQUE INDEX offers_one_accepted ON offers (property_id) WHERE status = 'accepted'");
            DB::statement("CREATE UNIQUE INDEX transactions_one_open ON transactions (property_id) WHERE status = 'open'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('transaction_milestones');
        Schema::dropIfExists('transaction_participants');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('offers');
    }
};
