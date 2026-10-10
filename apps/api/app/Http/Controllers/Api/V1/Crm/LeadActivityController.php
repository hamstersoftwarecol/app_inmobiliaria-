<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\Crm\LeadService;
use App\Domain\Crm\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadActivityResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Calls, e-mails, meetings and notes logged on a lead (append-only). */
class LeadActivityController extends Controller
{
    public function store(Request $request, Lead $lead, LeadService $leads): JsonResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, ActivityType::manual()))],
            'body' => ['nullable', 'required_if:type,note', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-1 year'],
        ]);

        $activity = $leads->logActivity(
            $request->user(),
            $lead,
            ActivityType::from($data['type']),
            $data['body'] ?? null,
            isset($data['occurred_at']) ? Carbon::parse($data['occurred_at'])->utc() : null,
        );

        return (new LeadActivityResource($activity->load('user')))->response()->setStatusCode(201);
    }
}
