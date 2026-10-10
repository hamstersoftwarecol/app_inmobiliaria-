<?php

namespace App\Models;

use App\Domain\Documents\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'version', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'uploaded_by'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['scan_status' => ScanStatus::class, 'scanned_at' => 'datetime', 'size' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
