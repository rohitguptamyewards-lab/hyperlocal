<?php

namespace App\Modules\RulesEngine\Services;

use App\Modules\RulesEngine\Constants\RulesDenyReason;
use App\Modules\RulesEngine\DTOs\RulesResult;
use App\Modules\RulesEngine\Models\CapCounter;
use App\Modules\Partnership\Models\PartnershipTerms;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Checks and atomically increments cap counters.
 *
 * All writes use DB::transaction + lockForUpdate() to prevent double-spend.
 * D-004 LOCKED: SELECT FOR UPDATE strategy.
 *
 * Two modes:
 *  - check()     : read-only eligibility check (no increment)
 *  - increment() : atomic write, returns RESULT_* constant
 *
 * increment() return values:
 *  RESULT_RACE_FAILED = 0 — cap was exhausted during the lock (race condition, roll back)
 *  RESULT_OK          = 1 — incremented, cap still has headroom
 *  RESULT_CAP_HIT     = 2 — incremented, cap is now AT or PAST the limit (fire alert event)
 */
class CapEnforcementService
{
    public const RESULT_RACE_FAILED = 0;
    public const RESULT_OK          = 1;
    public const RESULT_CAP_HIT     = 2;

    /**
     * Read-only cap check. Returns a deny result if any cap is exhausted,
     * or null if all caps have headroom.
     */
    public function check(
        PartnershipTerms $terms,
        int              $merchantId,
        int              $partnershipId,
        int              $outletId,
        float            $benefitAmount,
        Carbon           $asOf,
    ): ?RulesResult {
        $year  = (int) $asOf->format('Y');
        $month = (int) $asOf->format('n');

        // Global monthly cap
        if ($terms->monthly_cap_amount !== null) {
            $used = $this->getUsed($merchantId, $partnershipId, null, $year, $month);
            if ($used + $benefitAmount > $terms->monthly_cap_amount) {
                return RulesResult::deny(RulesDenyReason::MONTHLY_CAP_REACHED);
            }
        }

        // Partner-level monthly cap.
        // NOTE D-008: In standalone build, partner_monthly_cap and monthly_cap_amount
        // track the same counter. Check whichever is tighter (lower value wins).
        if ($terms->partner_monthly_cap !== null) {
            $used = $this->getUsed($merchantId, $partnershipId, null, $year, $month);
            if ($used + $benefitAmount > $terms->partner_monthly_cap) {
                return RulesResult::deny(RulesDenyReason::PARTNER_CAP_REACHED);
            }
        }

        // Outlet-level monthly cap
        if ($terms->outlet_monthly_cap !== null) {
            $used = $this->getUsed($merchantId, $partnershipId, $outletId, $year, $month);
            if ($used + $benefitAmount > $terms->outlet_monthly_cap) {
                return RulesResult::deny(RulesDenyReason::OUTLET_CAP_REACHED);
            }
        }

        // Daily cap (brand total) — queries partner_redemptions directly
        if ($terms->daily_cap_amount !== null) {
            $dailyUsed = DB::table('partner_redemptions')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $merchantId)
                ->where('status', 1)
                ->whereDate('created_at', $asOf->toDateString())
                ->sum('benefit_amount');
            if ((float) $dailyUsed + $benefitAmount > $terms->daily_cap_amount) {
                return RulesResult::deny(RulesDenyReason::DAILY_CAP_REACHED);
            }
        }

        // Per-outlet daily cap
        if ($terms->outlet_daily_cap_amount !== null) {
            $outletDailyUsed = DB::table('partner_redemptions')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $merchantId)
                ->where('outlet_id', $outletId)
                ->where('status', 1)
                ->whereDate('created_at', $asOf->toDateString())
                ->sum('benefit_amount');
            if ((float) $outletDailyUsed + $benefitAmount > $terms->outlet_daily_cap_amount) {
                return RulesResult::deny(RulesDenyReason::OUTLET_DAILY_CAP_REACHED);
            }
        }

        // Lifetime cap — total across all periods
        if ($terms->lifetime_cap_amount !== null) {
            $lifetimeUsed = DB::table('partner_redemptions')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $merchantId)
                ->where('status', 1)
                ->sum('benefit_amount');
            if ((float) $lifetimeUsed + $benefitAmount > $terms->lifetime_cap_amount) {
                return RulesResult::deny(RulesDenyReason::LIFETIME_CAP_REACHED);
            }
        }

        return null; // all caps have headroom
    }

    /**
     * Atomic increment inside a transaction with row-level lock.
     * Called ONLY after all other checks pass and redemption is confirmed.
     *
     * @return int RESULT_RACE_FAILED | RESULT_OK | RESULT_CAP_HIT
     */
    public function increment(
        PartnershipTerms $terms,
        int              $merchantId,
        int              $partnershipId,
        int              $outletId,
        float            $benefitAmount,
        Carbon           $asOf,
    ): int {
        $year  = (int) $asOf->format('Y');
        $month = (int) $asOf->format('n');

        return DB::transaction(function () use (
            $terms, $merchantId, $partnershipId, $outletId, $benefitAmount, $year, $month
        ): int {
            $capHit = false;

            // Lock and check global cap
            if ($terms->monthly_cap_amount !== null) {
                $counter = $this->getOrCreateCounter($merchantId, $partnershipId, null, $year, $month);
                $locked  = CapCounter::where('id', $counter->id)->lockForUpdate()->first();

                if ($locked->amount_used + $benefitAmount > $terms->monthly_cap_amount) {
                    return self::RESULT_RACE_FAILED; // race condition caught
                }

                $locked->increment('amount_used', $benefitAmount);
                $locked->increment('redemption_count');

                // Re-read after increment to detect exhaustion
                if ($locked->fresh()->amount_used >= $terms->monthly_cap_amount) {
                    $capHit = true;
                }
            }

            // Lock and check outlet cap
            if ($terms->outlet_monthly_cap !== null) {
                $counter = $this->getOrCreateCounter($merchantId, $partnershipId, $outletId, $year, $month);
                $locked  = CapCounter::where('id', $counter->id)->lockForUpdate()->first();

                if ($locked->amount_used + $benefitAmount > $terms->outlet_monthly_cap) {
                    return self::RESULT_RACE_FAILED;
                }

                $locked->increment('amount_used', $benefitAmount);
                $locked->increment('redemption_count');

                if ($locked->fresh()->amount_used >= $terms->outlet_monthly_cap) {
                    $capHit = true;
                }
            }

            // If no cap configured, still record usage for analytics
            if ($terms->monthly_cap_amount === null && $terms->outlet_monthly_cap === null) {
                $counter = $this->getOrCreateCounter($merchantId, $partnershipId, null, $year, $month);
                $counter->increment('amount_used', $benefitAmount);
                $counter->increment('redemption_count');
            }

            return $capHit ? self::RESULT_CAP_HIT : self::RESULT_OK;
        });
    }

    // -------------------------------------------------------------------------

    private function getUsed(
        int  $merchantId,
        int  $partnershipId,
        ?int $outletId,
        int  $year,
        int  $month,
    ): float {
        $row = CapCounter::where('merchant_id', $merchantId)
            ->where('partnership_id', $partnershipId)
            ->where('outlet_id', $outletId)
            ->forPeriod($year, $month)
            ->first();

        return $row ? $row->amount_used : 0.0;
    }

    private function getOrCreateCounter(
        int  $merchantId,
        int  $partnershipId,
        ?int $outletId,
        int  $year,
        int  $month,
    ): CapCounter {
        return CapCounter::firstOrCreate(
            [
                'merchant_id'    => $merchantId,
                'partnership_id' => $partnershipId,
                'outlet_id'      => $outletId,
                'period_year'    => $year,
                'period_month'   => $month,
            ],
            ['amount_used' => 0.00, 'redemption_count' => 0]
        );
    }
}
