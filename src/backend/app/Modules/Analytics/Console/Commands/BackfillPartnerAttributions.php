<?php

namespace App\Modules\Analytics\Console\Commands;

use App\Modules\Analytics\Services\AttributionBackfillService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Console\Command;

class BackfillPartnerAttributions extends Command
{
    protected $signature = 'analytics:backfill-attributions {--partnership= : Partnership UUID to limit the backfill} {--dry-run : Show the number of missing rows without writing data}';
    protected $description = 'Backfill missing partner_attributions rows from existing completed redemptions';

    public function __construct(private readonly AttributionBackfillService $backfill)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $partnershipId = null;

        if ($uuid = $this->option('partnership')) {
            $partnershipId = Partnership::where('uuid', $uuid)->valueOrFail('id');
        }

        $missing = $this->backfill->countMissing($partnershipId);

        if ($this->option('dry-run')) {
            $this->info("Missing attribution rows: {$missing}");
            return self::SUCCESS;
        }

        if ($missing === 0) {
            $this->info('No missing attribution rows found.');
            return self::SUCCESS;
        }

        $inserted = $this->backfill->backfill($partnershipId);
        $this->info("Backfilled {$inserted} attribution rows.");

        return self::SUCCESS;
    }
}
