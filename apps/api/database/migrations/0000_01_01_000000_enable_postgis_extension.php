<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS powers geographic search (bounding box, radius, polygon).
 * The extension must be available on the server (postgis/postgis image or
 * the postgresql-16-postgis-3 package).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        // Intentionally left in place: dropping PostGIS would cascade to every
        // geographic column and must be a deliberate DBA operation.
    }
};
