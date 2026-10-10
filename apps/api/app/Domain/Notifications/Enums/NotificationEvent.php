<?php

namespace App\Domain\Notifications\Enums;

/**
 * Optional e-mail categories users control. Security and account e-mails (password reset,
 * verification, sign-in alerts) are not optional and are not listed here. In-app notifications are
 * always stored.
 */
enum NotificationEvent: string
{
    case ShowingUpdates = 'showing_updates';
    case ShowingReminders = 'showing_reminders';
    case LeadUpdates = 'lead_updates';
    case TaskReminders = 'task_reminders';
    case SavedSearchAlerts = 'saved_search_alerts';
    case LicenceUpdates = 'licence_updates';
    case OfferUpdates = 'offer_updates';
    case TransactionUpdates = 'transaction_updates';
    case Messages = 'messages';
    case Marketing = 'marketing';

    /** Marketing needs explicit opt-in; everything else is on until turned off. */
    public function defaultMail(): bool
    {
        return $this !== self::Marketing;
    }
}
