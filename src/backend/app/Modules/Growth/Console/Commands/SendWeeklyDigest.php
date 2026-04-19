<?php

namespace App\Modules\Growth\Console\Commands;

use App\Modules\Growth\Services\WeeklyDigestService;
use Illuminate\Console\Command;

class SendWeeklyDigest extends Command
{
    protected $signature = 'growth:weekly-digest';
    protected $description = 'Generate and send weekly performance digest to all active merchants (Mondays)';

    public function handle(WeeklyDigestService $service): int
    {
        $count = $service->generateForAllMerchants();
        $this->info("Generated weekly digest for {$count} merchants.");
        return 0;
    }
}
