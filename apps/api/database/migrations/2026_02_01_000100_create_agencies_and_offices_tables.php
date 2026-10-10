<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('slug', 160)->unique();
            // Brokerage licence as entered by staff; not treated as verified.
            $table->string('license_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('timezone', 64)->default('America/New_York');
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('street_address', 200);
            $table->string('unit', 30)->nullable();
            $table->string('city', 100);
            $table->string('county', 100)->nullable();
            $table->char('state', 2)->default('NJ');
            $table->string('postal_code', 10);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['agency_id', 'slug']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Geography point derived from lat/lng for distance queries ("offices near me").
            DB::statement(<<<'SQL'
                ALTER TABLE offices ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (
                    CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                         THEN ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography
                    END
                ) STORED
            SQL);
            DB::statement('CREATE INDEX offices_location_gist ON offices USING GIST (location)');
            // Only one headquarters per agency.
            DB::statement('CREATE UNIQUE INDEX offices_one_hq_per_agency ON offices (agency_id) WHERE is_headquarters AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
        Schema::dropIfExists('agencies');
    }
};
