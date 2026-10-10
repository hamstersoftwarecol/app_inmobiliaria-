<?php

namespace Database\Seeders;

use App\Domain\Listings\Enums\PropertyTypeCode;
use App\Models\PropertyType;
use Illuminate\Database\Seeder;

/**
 * Reference data (all environments, idempotent).
 */
class PropertyTypesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PropertyTypeCode::cases() as $i => $code) {
            [$en, $es] = $code->names();
            PropertyType::query()->updateOrCreate(['code' => $code->value], ['name_en' => $en, 'name_es' => $es, 'sort_order' => $i]);
        }
    }
}
