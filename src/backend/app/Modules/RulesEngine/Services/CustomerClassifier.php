<?php

namespace App\Modules\RulesEngine\Services;

use App\Modules\RulesEngine\Constants\CustomerType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Classifies a customer as NEW, EXISTING, or REACTIVATED for a given outlet.
 *
 * Standalone build: uses partner_redemptions as proxy for visit history.
 * Post-eWards migration: this will query the full eWards transaction table.
 * The interface (classify method signature) stays the same — only the
 * data source changes on migration day.
 */
class CustomerClassifier
{
    /**
     * @param int|null $customerId      — null = treat as new (anonymous)
     * @param int      $merchantId      — outlet's merchant
     * @param int      $outletId        — the outlet being visited
     * @param int      $inactivityDays  — from partnership_rules (default 90)
     * @param Carbon   $asOf            — the moment of attempted redemption
     */
    public function classify(
        ?int   $customerId,
        int    $merchantId,
        int    $outletId,
        int    $inactivityDays,
        Carbon $asOf,
    ): int {
        // Anonymous customer → always treat as new
        if ($customerId === null) {
            return CustomerType::NEW;
        }

        // Check for any prior redemption at this merchant's outlets.
        // Queries member_id (set by Member module on all new redemptions).
        // customer_id was always NULL before the Member module was introduced.
        // TODO (post-migration): replace with eWards transaction history query.
        $history = DB::table('partner_redemptions')
            ->where('merchant_id', $merchantId)
            ->where('outlet_id', $outletId)
            ->where('member_id', $customerId)
            ->where('status', 1) // completed only
            ->selectRaw('MAX(created_at) as last_visit, COUNT(*) as visit_count')
            ->first();

        if (!$history || $history->visit_count === 0) {
            return CustomerType::NEW;
        }

        $lastVisit     = Carbon::parse($history->last_visit);
        $daysSinceLast = $lastVisit->diffInDays($asOf);

        if ($daysSinceLast > $inactivityDays) {
            return CustomerType::REACTIVATED;
        }

        return CustomerType::EXISTING;
    }
}
