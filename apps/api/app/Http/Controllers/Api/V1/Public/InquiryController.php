<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Application\Crm\LeadService;
use App\Domain\Crm\Enums\LeadSource;
use App\Http\Controllers\Concerns\ResolvesClientChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\InquiryRequest;
use App\Models\Agent;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

/**
 * Public contact forms (listing, agent profile, general). The response never reveals whether the
 * person was already known, and honeypot submissions get the same response without being stored.
 */
class InquiryController extends Controller
{
    use ResolvesClientChannel;

    public function store(InquiryRequest $request, LeadService $leads): JsonResponse
    {
        $accepted = response()->json(['message' => 'Thank you. An agent will contact you shortly.'], 202);
        if ($request->isSpam()) {
            return $accepted;
        }

        $data = $request->validated();
        $property = isset($data['property_slug'])
            ? Property::query()->publiclyVisible($this->channel($request))->where('slug', $data['property_slug'])->firstOrFail()
            : null;
        $agent = isset($data['agent_slug'])
            ? Agent::query()->publiclyListed()->where('slug', $data['agent_slug'])->firstOrFail()
            : null;
        $source = $property ? LeadSource::WebsiteListing : ($agent ? LeadSource::WebsiteAgent : LeadSource::WebsiteContact);

        $leads->recordInquiry($data, $source, $property, $agent, $request->ip());

        return $accepted;
    }
}
