<?php

namespace App\Modules\Analytics\Console\Commands;

use App\Modules\Analytics\Services\RetentionService;
use Illuminate\Console\Command;

/**
 * Scheduled daily: checks attributions whose 30/60/90-day window just matured
 * and marks customers as retained if they made a return visit.
 *
 * Owner module: Analytics
 * Schedule: daily (configured in routes/console.php)
 */
class UpdateRetentionFlags extends Command
{
    protected $signature   = 'analytics:update-retention';
    protected $description = 'Update 30/60/90-day retention flags on partner_attributions';

    public function __construct(private readonly RetentionService $retention) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Updating retention flags…');

        $results = $this->retention->updateFlags();

        $this->table(
            ['Window', 'Newly retained'],
            [
                ['30 days', $results['30d']],
                ['60 days', $results['60d']],
                ['90 days', $results['90d']],
            ]
        );

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
