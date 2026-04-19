<?php

namespace App\Modules\Enablement\Console\Commands;

use App\Modules\Enablement\Services\DormancyService;
use Illuminate\Console\Command;

/**
 * CheckDormancy — daily dormancy detection job.
 *
 * Owner module: Enablement
 * Schedule: daily at 03:00 (configured in routes/console.php)
 *
 * Usage:
 *   php artisan hyperlocal:check-dormancy
 */
class CheckDormancy extends Command
{
    protected $signature   = 'hyperlocal:check-dormancy';
    protected $description = 'Detect dormant partnership outlets and fire PartnershipDormant events';

    public function __construct(private readonly DormancyService $dormancy)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking partnership dormancy…');

        $stats = $this->dormancy->checkAll();

        $this->table(
            ['Outlets checked', 'Newly dormant', 'Alerts sent', 'Recovered'],
            [[
                $stats['checked'],
                $stats['newly_dormant'],
                $stats['alerts_sent'],
                $stats['recovered'],
            ]],
        );

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
