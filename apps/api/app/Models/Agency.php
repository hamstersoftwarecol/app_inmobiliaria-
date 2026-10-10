<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'legal_name', 'slug', 'license_number', 'email', 'phone', 'website', 'timezone', 'settings'])]
class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /**
     * @return HasMany<Office, $this>
     */
    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    /**
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
