<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /**
     * Builds a URL slug unique within the given query scope (soft-deleted rows included).
     *
     * @param  Builder<Model>  $scope
     */
    public static function for(string $source, Builder $scope, string $column = 'slug', ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $base = mb_substr($base, 0, 150);
        $slug = $base;
        $i = 2;

        $exists = fn (string $candidate) => (clone $scope)
            ->when(method_exists($scope->getModel(), 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where($column, $candidate)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        while ($exists($slug)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
