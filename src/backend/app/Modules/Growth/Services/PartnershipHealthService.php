<?php

namespace App\Modules\Growth\Services;

use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Facades\DB;

/**
 * Computes partnership health scores (0-100) based on:
 *   - Reciprocity (are both sides sending customers?)
 *   - Activity (recent redemptions in last 30 days)
 *   - Cap utilization (how much of monthly cap is being used)
 *   - ROI (revenue vs benefit cost)
 *
 * Features: #7 Partnership Health Score, #4 Leaderboard
 */
class PartnershipHealthService
{
    public function computeAll(): int
    {
        $partnerships = DB::table('partnerships')
            ->where('status', PartnershipStatus::LIVE)
            ->whereNull('deleted_at')
            ->pluck('id');

        $count = 0;
        foreach ($partnerships as $pid) {
            $this->computeForPartnership($pid);
            $count++;
        }
        return $count;
    }

    public function computeForPartnership(int $partnershipId): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Activity: redemptions in last 30 days
        $recentRedemptions = DB::table('partner_redemptions')
            ->where('partnership_id', $partnershipId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $activityScore = min(100, $recentRedemptions * 5); // 20+ redemptions = 100

        // Reciprocity: ratio of customers sent vs received
        $participants = DB::table('partnership_participants')
            ->where('partnership_id', $partnershipId)
            ->whereNull('deleted_at')
            ->pluck('merchant_id');

        $reciprocityScore = 50; // default balanced
        if ($participants->count() === 2) {
            $m1 = $participants[0];
            $m2 = $participants[1];
            $sent1 = DB::table('partner_redemptions')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $m2) // m2 received = m1 sent
                ->where('created_at', '>=', $thirtyDaysAgo)->count();
            $sent2 = DB::table('partner_redemptions')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $m1)
                ->where('created_at', '>=', $thirtyDaysAgo)->count();
            $total = $sent1 + $sent2;
            if ($total > 0) {
                $ratio = min($sent1, $sent2) / max($sent1, $sent2, 1);
                $reciprocityScore = (int) ($ratio * 100);
            }
        }

        // ROI: revenue vs cost
        $revenue = (float) DB::table('partner_redemptions')
            ->where('partnership_id', $partnershipId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('bill_amount');
        $cost = (float) DB::table('partner_redemptions')
            ->where('partnership_id', $partnershipId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('benefit_amount');
        $roiScore = $cost > 0 ? min(100, (int) (($revenue / $cost) * 25)) : 50;

        // Cap utilization
        $capUsed = (float) DB::table('partnership_cap_counters')
            ->where('partnership_id', $partnershipId)
            ->where('period_year', now()->year)
            ->where('period_month', now()->month)
            ->sum('amount_used');
        $capMax = (float) DB::table('partnership_terms')
            ->where('partnership_id', $partnershipId)
            ->value('monthly_cap_amount') ?? 10000;
        $capUtilScore = $capMax > 0 ? min(100, (int) (($capUsed / $capMax) * 100)) : 50;

        $overall = (int) (($activityScore * 0.3) + ($reciprocityScore * 0.25) + ($roiScore * 0.25) + ($capUtilScore * 0.2));
        $level = $overall >= 70 ? 'green' : ($overall >= 40 ? 'yellow' : 'red');

        $factors = compact('activityScore', 'reciprocityScore', 'roiScore', 'capUtilScore');

        DB::table('partnership_health_scores')->updateOrInsert(
            ['partnership_id' => $partnershipId, 'scored_at' => now()->toDateString()],
            ['score' => $overall, 'level' => $level, 'factors' => json_encode($factors), 'updated_at' => now()],
        );

        return ['score' => $overall, 'level' => $level, 'factors' => $factors];
    }

    public function getLeaderboard(int $merchantId, int $limit = 5): array
    {
        return DB::table('partnership_health_scores as phs')
            ->join('partnerships as p', 'p.id', '=', 'phs.partnership_id')
            ->join('partnership_participants as pp', function ($j) use ($merchantId) {
                $j->on('pp.partnership_id', '=', 'p.id')
                  ->where('pp.merchant_id', $merchantId)
                  ->whereNull('pp.deleted_at');
            })
            ->where('phs.scored_at', now()->toDateString())
            ->where('p.status', PartnershipStatus::LIVE)
            ->orderByDesc('phs.score')
            ->limit($limit)
            ->select(['p.name', 'phs.score', 'phs.level', 'phs.factors'])
            ->get()
            ->toArray();
    }

    public function getRepeatRate(int $partnershipId, int $merchantId): array
    {
        $firstVisitors = DB::table('partner_attributions')
            ->where('partnership_id', $partnershipId)
            ->where('target_merchant_id', $merchantId)
            ->where('customer_type', 1) // NEW
            ->count();

        $retained30 = DB::table('partner_attributions')
            ->where('partnership_id', $partnershipId)
            ->where('target_merchant_id', $merchantId)
            ->where('customer_type', 1)
            ->where('retained_30d', true)
            ->count();

        $retained60 = DB::table('partner_attributions')
            ->where('partnership_id', $partnershipId)
            ->where('target_merchant_id', $merchantId)
            ->where('customer_type', 1)
            ->where('retained_60d', true)
            ->count();

        $retained90 = DB::table('partner_attributions')
            ->where('partnership_id', $partnershipId)
            ->where('target_merchant_id', $merchantId)
            ->where('customer_type', 1)
            ->where('retained_90d', true)
            ->count();

        return [
            'first_visitors' => $firstVisitors,
            'retained_30d'   => $firstVisitors > 0 ? round(($retained30 / $firstVisitors) * 100, 1) : 0,
            'retained_60d'   => $firstVisitors > 0 ? round(($retained60 / $firstVisitors) * 100, 1) : 0,
            'retained_90d'   => $firstVisitors > 0 ? round(($retained90 / $firstVisitors) * 100, 1) : 0,
        ];
    }
}
