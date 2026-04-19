<?php

namespace App\Modules\Discovery\Console\Commands;

use App\Modules\Discovery\Services\RecommendationService;
use Illuminate\Console\Command;

/**
 * ComputeRecommendations — nightly batch that computes partner fit scores.
 *
 * Owner module: Discovery
 * Schedule: nightly at 01:00 (configured in routes/console.php)
 *
 * Usage:
 *   php artisan hyperlocal:compute-recommendations              # all merchants
 *   php artisan hyperlocal:compute-recommendations --merchant_id=1  # one merchant
 */
class ComputeRecommendations extends Command
{
    protected $signature   = 'hyperlocal:compute-recommendations {--merchant_id= : Restrict to a single merchant ID}';
    protected $description = 'Pre-compute partner fit scores and upsert partner_recommendations';

    public function __construct(private readonly RecommendationService $recommendations)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $merchantId = $this->option('merchant_id')
            ? (int) $this->option('merchant_id')
            : null;

        $label = $merchantId ? "merchant #{$merchantId}" : 'all merchants';
        $this->info("Computing partner recommendations for {$label}…");

        $result = $this->recommendations->compute($merchantId);

        $this->table(
            ['Merchants processed', 'Recommendations stored'],
            [[$result['processed'], $result['stored']]],
        );

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
