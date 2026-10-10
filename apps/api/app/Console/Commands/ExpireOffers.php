<?php

namespace App\Console\Commands;

use App\Application\Deals\OfferService;
use Illuminate\Console\Command;

class ExpireOffers extends Command
{
    protected $signature = 'app-inmobiliaria:offers:expire';

    protected $description = 'Mark submitted offers past their response deadline as expired';

    public function handle(OfferService $offers): int
    {
        $this->info('Expired '.$offers->expireDue().' offer(s).');

        return self::SUCCESS;
    }
}
