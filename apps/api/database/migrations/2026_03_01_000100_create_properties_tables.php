<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en', 80);
            $table->string('name_es', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('mls_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mls_provider_id')->constrained()->cascadeOnDelete();
            $table->string('listing_key', 100);
            $table->string('listing_id', 100)->nullable();
            $table->string('originating_system', 150)->nullable();
            $table->string('status', 30)->index();
            $table->decimal('list_price', 14, 2)->nullable();
            $table->string('listing_broker', 200)->nullable();
            $table->string('listing_agent', 200)->nullable();
            $table->text('attribution')->nullable();
            $table->boolean('internet_display_allowed')->default(true);
            $table->boolean('address_display_allowed')->default(true);
            $table->timestampTz('source_modified_at')->nullable();
            $table->timestampTz('last_synced_at');
            $table->timestampTz('removed_at')->nullable()->index();
            $table->string('content_hash', 64);
            $table->jsonb('raw_payload');
            $table->timestampsTz();
            // Listing keys are only unique within one MLS.
            $table->unique(['mls_provider_id', 'listing_key']);
            $table->index(['mls_provider_id', 'listing_id']);
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('source', 10)->default('internal');
            $table->foreignId('mls_listing_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('listing_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('property_subtype', 80)->nullable();
            $table->string('listing_type', 10)->default('sale')->index();
            $table->string('status', 30)->default('active')->index();
            $table->boolean('is_published')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('description_es')->nullable();
            $table->decimal('list_price', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('street_address', 200)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('county', 100)->nullable();
            $table->char('state', 2)->default('NJ');
            $table->string('postal_code', 10)->nullable()->index();
            $table->boolean('address_display')->default(true);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 4, 1)->nullable();
            $table->unsignedInteger('living_area_sqft')->nullable();
            $table->decimal('lot_size_sqft', 12, 2)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->unsignedSmallInteger('garage_spaces')->nullable();
            $table->jsonb('heating')->nullable();
            $table->jsonb('cooling')->nullable();
            $table->jsonb('amenities')->nullable();
            $table->decimal('hoa_fee_monthly', 10, 2)->nullable();
            $table->decimal('annual_taxes', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['is_published', 'status', 'list_price']);
            $table->index('listing_agent_id');
        });

        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10);
            $table->string('mls_media_key', 150)->nullable();
            // MLS media: provider HTTPS URL. Uploads: object key on the media disk.
            $table->string('url', 1000)->nullable();
            $table->string('disk', 30)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('caption', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('display_allowed')->default(true);
            $table->timestampsTz();
            $table->unique(['property_id', 'mls_media_key']);
            $table->index(['property_id', 'sort_order']);
        });

        Schema::create('property_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->decimal('from_price', 14, 2)->nullable();
            $table->decimal('to_price', 14, 2)->nullable();
            $table->string('source', 10);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('changed_at')->useCurrent();
            $table->index(['property_id', 'changed_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE properties ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (
                    CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                         THEN ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography
                    END
                ) STORED
            SQL);
            DB::statement('CREATE INDEX properties_location_gist ON properties USING GIST (location)');
            // Hot path for public pages: published, not deleted.
            DB::statement('CREATE INDEX properties_public_idx ON properties (status, list_price) WHERE is_published AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_status_history');
        Schema::dropIfExists('property_media');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('mls_listings');
        Schema::dropIfExists('property_types');
    }
};
