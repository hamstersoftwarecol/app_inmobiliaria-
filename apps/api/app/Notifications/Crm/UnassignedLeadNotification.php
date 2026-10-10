<?php

namespace App\Notifications\Crm;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app notice to staff who can assign leads when an inquiry has no obvious agent. */
class UnassignedLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'lead.unassigned', 'lead_id' => $this->lead->id, 'name' => $this->lead->full_name, 'source' => $this->lead->source->value];
    }
}
