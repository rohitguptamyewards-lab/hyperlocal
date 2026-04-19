<?php

namespace App\Modules\Growth\Console\Commands;

use App\Modules\Growth\Services\PartnershipHealthService;
use Illuminate\Console\Command;

class ComputeHealthScores extends Command
{
    protected $signature = 'growth:health-scores';
    protected $description = 'Compute partnership health scores for all LIVE partnerships (daily)';

    public function handle(PartnershipHealthService $service): int
    {
        $count = $service->computeAll();
        $this->info("Computed health scores for {$count} partnerships.");
        return 0;
    }
}
