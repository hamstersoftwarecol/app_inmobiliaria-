<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\AuditCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(AuditCategory::class)],
            'event' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,first_name,last_name,email')
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', 'like', addcslashes($v, '%_\\').'%'))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
