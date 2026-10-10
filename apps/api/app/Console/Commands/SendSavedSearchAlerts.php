<?php

namespace App\Console\Commands;

use App\Application\Search\SavedSearchAlerts;
use App\Domain\Search\Enums\AlertFrequency;
use Illuminate\Console\Command;

class SendSavedSearchAlerts extends Command
{
    protected $signature = 'hitlease:saved-searches:alert {--frequency=daily : daily or weekly}';

    protected $description = 'Notify users about new listings matching their saved searches';

    public function handle(SavedSearchAlerts $alerts): int
    {
        $frequency = AlertFrequency::tryFrom((string) $this->option('frequency'));
        if ($frequency === null || $frequency === AlertFrequency::Off) {
            $this->error('Frequency must be daily or weekly.');

            return self::INVALID;
        }

        $stats = $alerts->run($frequency);
        $this->info("Checked {$stats['searches']} saved search(es); notified {$stats['notified']}.");

        return self::SUCCESS;
    }
}
