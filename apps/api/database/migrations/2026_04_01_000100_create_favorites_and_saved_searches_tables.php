<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['user_id', 'property_id']);
            $table->index('property_id');
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->jsonb('filters');
            $table->string('sort', 20)->nullable();
            $table->string('alert_frequency', 10)->default('daily');
            $table->timestampTz('last_alerted_at')->nullable();
            $table->timestampsTz();
            $table->index(['alert_frequency', 'last_alerted_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('favorites');
    }
};
