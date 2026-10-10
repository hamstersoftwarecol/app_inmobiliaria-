<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, PropertyTypesSeeder::class]);

        if (app()->environment(['local'])) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
