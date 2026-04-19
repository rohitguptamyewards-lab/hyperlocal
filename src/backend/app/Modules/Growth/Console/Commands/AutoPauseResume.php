<?php

namespace App\Modules\Growth\Console\Commands;

use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Feature #8: Auto-pause when cap is 80%+ utilized.
 * Notify merchant when cap is about to be exhausted.
 * Auto-resume on new month (cap resets) if partnership was auto-paused.
 */
class AutoPauseResume extends Command
{
    protected $signature = 'growth:auto-pause-resume';
    protected $description = 'Auto-pause partnerships at 80%+ cap usage, auto-resume on cap reset';

    public function handle(): int
    {
        $year  = now()->year;
        $month = now()->month;
        $paused = 0;
        $resumed = 0;

        // Find LIVE partnerships where cap usage > 80%
        $livePartnerships = DB::table('partnerships')
            ->where('status', PartnershipStatus::LIVE)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        foreach ($livePartnerships as $p) {
            $terms = DB::table('partnership_terms')
                ->where('partnership_id', $p->id)
                ->first();

            if (!$terms || !$terms->monthly_cap_amount) continue;

            $used = (float) DB::table('partnership_cap_counters')
                ->where('partnership_id', $p->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->sum('amount_used');

            $utilization = $used / $terms->monthly_cap_amount;

            if ($utilization >= 0.8 && $terms->pause_on_monthly_limit) {
                DB::table('partnerships')
                    ->where('id', $p->id)
                    ->update([
                        'status'        => PartnershipStatus::PAUSED,
                        'paused_at'     => now(),
                        'paused_reason' => 'Auto-paused: monthly cap 80%+ utilized',
                        'updated_at'    => now(),
                    ]);
                Log::info("AutoPause: Partnership {$p->name} paused at {$utilization}% cap usage");
                $paused++;
            }
        }

        // Auto-resume: partnerships paused due to cap that now have a new month
        $pausedPartnerships = DB::table('partnerships')
            ->where('status', PartnershipStatus::PAUSED)
            ->where('paused_reason', 'LIKE', '%Auto-paused%')
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        foreach ($pausedPartnerships as $p) {
            $used = (float) DB::table('partnership_cap_counters')
                ->where('partnership_id', $p->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->sum('amount_used');

            if ($used == 0) {
                DB::table('partnerships')
                    ->where('id', $p->id)
                    ->update([
                        'status'        => PartnershipStatus::LIVE,
                        'paused_at'     => null,
                        'paused_reason' => null,
                        'updated_at'    => now(),
                    ]);
                Log::info("AutoResume: Partnership {$p->name} resumed (new month, cap reset)");
                $resumed++;
            }
        }

        $this->info("Auto-pause: {$paused} partnerships paused. Auto-resume: {$resumed} partnerships resumed.");
        return 0;
    }
}
