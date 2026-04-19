<?php

namespace App\Modules\Growth\Services;

use Illuminate\Support\Facades\DB;

/**
 * Feature #16: Hyperlocal Demand Index.
 * Aggregates cross-merchant foot traffic data to produce demand insights.
 * Feature #17: Predictive Partner Matching — category-level ROI data.
 */
class DemandIndexService
{
    /**
     * Category demand by city — "Coffee demand near gyms in Bandra is 3x average"
     */
    public function getCategoryDemand(string $city): array
    {
        return DB::table('partner_redemptions as r')
            ->join('merchants as m', 'm.id', '=', 'r.merchant_id')
            ->where('m.city', $city)
            ->where('r.status', 1)
            ->where('r.created_at', '>=', now()->subMonths(3))
            ->groupBy('m.category')
            ->select([
                'm.category',
                DB::raw('COUNT(*) as redemptions'),
                DB::raw('COUNT(DISTINCT r.merchant_id) as merchants'),
                DB::raw('SUM(r.bill_amount) as total_revenue'),
                DB::raw('AVG(r.bill_amount) as avg_bill'),
            ])
            ->orderByDesc('redemptions')
            ->get()
            ->toArray();
    }

    /**
     * Best-performing category combinations — "gym + cafe partnerships have 40% better ROI"
     */
    public function getCategoryPairPerformance(): array
    {
        return DB::table('partnerships as p')
            ->join('partnership_participants as pp1', 'pp1.partnership_id', '=', 'p.id')
            ->join('partnership_participants as pp2', function ($j) {
                $j->on('pp2.partnership_id', '=', 'p.id')
                  ->whereColumn('pp2.merchant_id', '!=', 'pp1.merchant_id');
            })
            ->join('merchants as m1', 'm1.id', '=', 'pp1.merchant_id')
            ->join('merchants as m2', 'm2.id', '=', 'pp2.merchant_id')
            ->where('p.status', 5) // LIVE
            ->whereNull('pp1.deleted_at')
            ->whereNull('pp2.deleted_at')
            ->groupBy('m1.category', 'm2.category')
            ->select([
                'm1.category as category_a',
                'm2.category as category_b',
                DB::raw('COUNT(DISTINCT p.id) as partnership_count'),
            ])
            ->having('partnership_count', '>', 0)
            ->orderByDesc('partnership_count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Untapped demand for a merchant — categories with high demand near them but no partnership
     */
    public function getUntappedDemand(int $merchantId): array
    {
        $merchant = DB::table('merchants')->find($merchantId);
        if (!$merchant) return [];

        $existingPartnerCategories = DB::table('partnership_participants as pp')
            ->join('partnerships as p', 'p.id', '=', 'pp.partnership_id')
            ->join('partnership_participants as pp2', function ($j) use ($merchantId) {
                $j->on('pp2.partnership_id', '=', 'p.id')
                  ->where('pp2.merchant_id', '!=', $merchantId);
            })
            ->join('merchants as m', 'm.id', '=', 'pp2.merchant_id')
            ->where('pp.merchant_id', $merchantId)
            ->whereIn('p.status', [2, 3, 4, 5]) // any active state
            ->pluck('m.category')
            ->unique()
            ->toArray();

        $demand = DB::table('merchants')
            ->where('city', $merchant->city)
            ->where('id', '!=', $merchantId)
            ->whereNotIn('category', array_merge($existingPartnerCategories, [$merchant->category]))
            ->where('ecosystem_active', true)
            ->groupBy('category')
            ->select(['category', DB::raw('COUNT(*) as merchant_count')])
            ->orderByDesc('merchant_count')
            ->limit(5)
            ->get()
            ->toArray();

        return $demand;
    }
}
