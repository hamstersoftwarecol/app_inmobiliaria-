<?php

namespace App\Models;

use App\Domain\Documents\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['transaction_id', 'title', 'category', 'visibility', 'retain_until', 'created_by'];

    protected $attributes = ['visibility' => 'internal'];

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class, 'retain_until' => 'date'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version', 'max');
    }

    public function isShared(): bool
    {
        return $this->visibility === 'shared';
    }
}
