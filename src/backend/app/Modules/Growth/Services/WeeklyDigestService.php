<?php

namespace App\Modules\Growth\Services;

use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Feature #3: Weekly performance email digest.
 * Computes last-7-day metrics per merchant, logs digest (email send is future work).
 */
class WeeklyDigestService
{
    public function generateForMerchant(int $merchantId): array
    {
        $weekAgo = now()->subDays(7);

        $newCustomers = (int) DB::table('partner_attributions')
            ->where('target_merchant_id', $merchantId)
            ->where('customer_type', 1)
            ->where('attributed_at', '>=', $weekAgo)
            ->count();

        $revenue = (float) DB::table('partner_redemptions')
            ->where('merchant_id', $merchantId)
            ->where('created_at', '>=', $weekAgo)
            ->where('status', 1)
            ->sum('bill_amount');

        $benefitCost = (float) DB::table('partner_redemptions')
            ->where('merchant_id', $merchantId)
            ->where('created_at', '>=', $weekAgo)
            ->where('status', 1)
            ->sum('benefit_amount');

        $totalRedemptions = (int) DB::table('partner_redemptions')
            ->where('merchant_id', $merchantId)
            ->where('created_at', '>=', $weekAgo)
            ->count();

        // Best partnership this week
        $bestPartnership = DB::table('partner_redemptions as r')
            ->join('partnerships as p', 'p.id', '=', 'r.partnership_id')
            ->where('r.merchant_id', $merchantId)
            ->where('r.created_at', '>=', $weekAgo)
            ->groupBy('r.partnership_id', 'p.name')
            ->orderByRaw('SUM(r.bill_amount) DESC')
            ->select(['p.name', DB::raw('SUM(r.bill_amount) as revenue'), DB::raw('COUNT(*) as redemptions')])
            ->first();

        $digest = [
            'merchant_id'       => $merchantId,
            'period'            => 'last_7_days',
            'new_customers'     => $newCustomers,
            'revenue'           => $revenue,
            'benefit_cost'      => $benefitCost,
            'net_value'         => $revenue - $benefitCost,
            'total_redemptions' => $totalRedemptions,
            'best_partnership'  => $bestPartnership ? [
                'name'        => $bestPartnership->name,
                'revenue'     => (float) $bestPartnership->revenue,
                'redemptions' => (int) $bestPartnership->redemptions,
            ] : null,
        ];

        Log::info('WeeklyDigest generated', $digest);

        return $digest;
    }

    public function generateForAllMerchants(): int
    {
        $merchantIds = DB::table('partnership_participants')
            ->join('partnerships', 'partnerships.id', '=', 'partnership_participants.partnership_id')
            ->where('partnerships.status', PartnershipStatus::LIVE)
            ->whereNull('partnership_participants.deleted_at')
            ->distinct()
            ->pluck('partnership_participants.merchant_id');

        foreach ($merchantIds as $mid) {
            $this->generateForMerchant($mid);
        }

        return $merchantIds->count();
    }
}
