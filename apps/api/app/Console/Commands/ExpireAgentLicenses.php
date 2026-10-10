<?php

namespace App\Console\Commands;

use App\Application\Agency\LicenseVerificationService;
use Illuminate\Console\Command;

class ExpireAgentLicenses extends Command
{
    protected $signature = 'hitlease:licenses:expire';

    protected $description = 'Mark verified agent licences past their expiration date as expired';

    public function handle(LicenseVerificationService $service): int
    {
        $count = $service->expireDue();
        $this->info("Expired {$count} licence(s).");

        return self::SUCCESS;
    }
}
