<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PartnerAttribution;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\RulesEngine\Constants\CustomerType;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Calculates ROI, attribution, trends, and revenue metrics per partnership.
 *
 * Owner module: Analytics
 * Reads: partner_attributions, partner_ledger_entries, partner_redemptions,
 *        partner_claims, campaigns, campaign_sends, partnerships
 * Writes: nothing
 */
class RoiService
{
    /**
     * Per-partnership analytics summary from a merchant's perspective.
     */
    public function forPartnership(int $partnershipId, int $merchantId): array
    {
        $attributions = PartnerAttribution::where('partnership_id', $partnershipId)
            ->where(function ($q) use ($merchantId) {
                $q->where('source_merchant_id', $merchantId)
                  ->orWhere('target_merchant_id', $merchantId);
            })
            ->get();

        $targetSide = $attributions->where('target_merchant_id', $merchantId);
        $sourceSide = $attributions->where('source_merchant_id', $merchantId);

        $newCount          = $targetSide->where('customer_type', CustomerType::NEW)->count();
        $retained30        = $targetSide->where('customer_type', CustomerType::NEW)->where('retained_30d', true)->count();
        $retained60        = $targetSide->where('customer_type', CustomerType::NEW)->where('retained_60d', true)->count();
        $retained90        = $targetSide->where('customer_type', CustomerType::NEW)->where('retained_90d', true)->count();

        $totalBenefitGiven    = $targetSide->sum('benefit_amount');
        $totalReferralCredits = $sourceSide->sum('benefit_amount');

        // Revenue from partner's customers visiting you (bill amounts)
        $totalRevenue = DB::table('partner_redemptions')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId) // you are the target
            ->where('status', 1) // completed
            ->sum('bill_amount');

        $avgBillAmount = DB::table('partner_redemptions')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->avg('bill_amount');

        // Claim conversion rate
        $claimsIssued = DB::table('partner_claims')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->count();
        $claimsRedeemed = DB::table('partner_claims')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->where('status', 2) // redeemed
            ->count();

        // Monthly trend (last 6 months)
        $monthlyTrend = $this->partnershipTrend($partnershipId, $merchantId, 6);

        return [
            'total_redemptions'     => $attributions->count(),
            'new_customers'         => $newCount,
            'existing_customers'    => $targetSide->where('customer_type', CustomerType::EXISTING)->count(),
            'reactivated_customers' => $targetSide->where('customer_type', CustomerType::REACTIVATED)->count(),
            'customers_sent'        => $sourceSide->count(),
            'total_benefit_given'   => round((float) $totalBenefitGiven, 2),
            'total_referral_credits'=> round((float) $totalReferralCredits, 2),
            'total_revenue'         => round((float) $totalRevenue, 2),
            'avg_bill_amount'       => $avgBillAmount ? round((float) $avgBillAmount, 2) : null,
            'cost_per_customer'     => $newCount > 0 ? round($totalBenefitGiven / $newCount, 2) : null,
            'claim_conversion_rate' => $claimsIssued > 0 ? round($claimsRedeemed / $claimsIssued * 100, 1) : null,
            'retained_30d_count'    => $retained30,
            'retained_60d_count'    => $retained60,
            'retained_90d_count'    => $retained90,
            'retention_30d_rate'    => $newCount > 0 ? round($retained30 / $newCount * 100, 1) : null,
            'retention_60d_rate'    => $newCount > 0 ? round($retained60 / $newCount * 100, 1) : null,
            'retention_90d_rate'    => $newCount > 0 ? round($retained90 / $newCount * 100, 1) : null,
            'roi_score'             => $totalBenefitGiven > 0
                ? round($totalReferralCredits / $totalBenefitGiven, 2)
                : null,
            'monthly_trend'         => $monthlyTrend,
        ];
    }

    /**
     * Dashboard-level summary: comprehensive merchant analytics.
     */
    public function dashboardSummary(int $merchantId): array
    {
        $targetRows = PartnerAttribution::where('target_merchant_id', $merchantId)->get();
        $sourceRows = PartnerAttribution::where('source_merchant_id', $merchantId)->get();

        // Revenue from network (bill amounts from partner-referred customers)
        $totalRevenue = DB::table('partner_redemptions')
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->sum('bill_amount');

        // Loyalty liability (your customers' points used at partner stores — your cost)
        $loyaltyLiability = DB::table('partner_ledger_entries')
            ->where('merchant_id', $merchantId)
            ->where('entry_type', 'referral_credit') // credits you gave away
            ->sum('amount');

        // Campaign stats (safe — table may not exist yet)
        $campaignsSent = 0;
        $messagesDelivered = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('campaigns')) {
                $campaignsSent = DB::table('campaigns')
                    ->where('merchant_id', $merchantId)
                    ->where('status', 4)
                    ->count();
                $messagesDelivered = DB::table('campaign_sends')
                    ->join('campaigns', 'campaigns.id', '=', 'campaign_sends.campaign_id')
                    ->where('campaigns.merchant_id', $merchantId)
                    ->where('campaign_sends.status', 4)
                    ->count();
            }
        } catch (\Exception $e) {
            // campaigns table not yet migrated
        }

        // Monthly trend (last 6 months)
        $monthlyTrend = $this->dashboardTrend($merchantId, 6);

        // Per-partnership breakdown
        $partnerships = Partnership::forMerchant($merchantId)
            ->where('status', 5) // LIVE
            ->get();

        $partnerBreakdown = [];
        foreach ($partnerships as $p) {
            $pTarget = $targetRows->where('partnership_id', $p->id);
            $pSource = $sourceRows->where('partnership_id', $p->id);

            $pRevenue = DB::table('partner_redemptions')
                ->where('partnership_id', $p->id)
                ->where('merchant_id', $merchantId)
                ->where('status', 1)
                ->sum('bill_amount');

            $partnerBreakdown[] = [
                'partnership_uuid' => $p->uuid,
                'name'             => $p->name,
                'new_customers'    => $pTarget->where('customer_type', CustomerType::NEW)->count(),
                'existing_customers' => $pTarget->where('customer_type', CustomerType::EXISTING)->count(),
                'total_redemptions'=> $pTarget->count(),
                'revenue'          => round((float) $pRevenue, 2),
                'benefit_given'    => round((float) $pTarget->sum('benefit_amount'), 2),
                'customers_sent'   => $pSource->count(),
                'roi_score'        => $pTarget->sum('benefit_amount') > 0
                    ? round($pSource->sum('benefit_amount') / $pTarget->sum('benefit_amount'), 2)
                    : null,
            ];
        }

        return [
            // Core metrics
            'live_partnerships'      => $partnerships->count(),
            'total_redemptions'      => $targetRows->count(),
            'new_customers'          => $targetRows->where('customer_type', CustomerType::NEW)->count(),
            'existing_customers'     => $targetRows->where('customer_type', CustomerType::EXISTING)->count(),
            'reactivated_customers'  => $targetRows->where('customer_type', CustomerType::REACTIVATED)->count(),
            'customers_sent'         => $sourceRows->count(),

            // Financial
            'total_benefit_given'    => round((float) $targetRows->sum('benefit_amount'), 2),
            'total_revenue_from_network' => round((float) $totalRevenue, 2),
            'total_loyalty_liability'    => round((float) $loyaltyLiability, 2),
            'net_value'              => round((float) $totalRevenue - (float) $targetRows->sum('benefit_amount'), 2),

            // Campaigns
            'campaigns_sent'         => $campaignsSent,
            'messages_delivered'     => $messagesDelivered,

            // Trend
            'monthly_trend'          => $monthlyTrend,

            // Per-partner
            'partner_breakdown'      => $partnerBreakdown,
        ];
    }

    /**
     * Monthly trend for dashboard — last N months.
     */
    public function dashboardTrend(int $merchantId, int $months = 6): array
    {
        $since = Carbon::now()->subMonths($months)->startOfMonth();

        return DB::table('partner_attributions')
            ->where('target_merchant_id', $merchantId)
            ->where('period_month', '>=', $since->format('Y-m-d'))
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->select([
                'period_month as month',
                DB::raw('COUNT(*) as redemptions'),
                DB::raw('SUM(CASE WHEN customer_type = ' . CustomerType::NEW . ' THEN 1 ELSE 0 END) as new_customers'),
                DB::raw('SUM(benefit_amount) as benefit_given'),
            ])
            ->get()
            ->map(function ($row) use ($merchantId) {
                // Add revenue for this month
                $revenue = DB::table('partner_redemptions')
                    ->where('merchant_id', $merchantId)
                    ->where('status', 1)
                    ->whereRaw("strftime('%Y-%m-01', created_at) = ?", [$row->month])
                    ->sum('bill_amount');

                return [
                    'month'         => $row->month,
                    'redemptions'   => (int) $row->redemptions,
                    'new_customers' => (int) $row->new_customers,
                    'benefit_given' => round((float) $row->benefit_given, 2),
                    'revenue'       => round((float) $revenue, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Monthly trend for a specific partnership.
     */
    public function partnershipTrend(int $partnershipId, int $merchantId, int $months = 6): array
    {
        $since = Carbon::now()->subMonths($months)->startOfMonth();

        return DB::table('partner_attributions')
            ->where('partnership_id', $partnershipId)
            ->where('target_merchant_id', $merchantId)
            ->where('period_month', '>=', $since->format('Y-m-d'))
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->select([
                'period_month as month',
                DB::raw('COUNT(*) as redemptions'),
                DB::raw('SUM(CASE WHEN customer_type = ' . CustomerType::NEW . ' THEN 1 ELSE 0 END) as new_customers'),
                DB::raw('SUM(benefit_amount) as benefit_given'),
            ])
            ->get()
            ->map(function ($row) use ($partnershipId, $merchantId) {
                $revenue = DB::table('partner_redemptions')
                    ->where('partnership_id', $partnershipId)
                    ->where('merchant_id', $merchantId)
                    ->where('status', 1)
                    ->whereRaw("strftime('%Y-%m-01', created_at) = ?", [$row->month])
                    ->sum('bill_amount');

                return [
                    'month'         => $row->month,
                    'redemptions'   => (int) $row->redemptions,
                    'new_customers' => (int) $row->new_customers,
                    'benefit_given' => round((float) $row->benefit_given, 2),
                    'revenue'       => round((float) $revenue, 2),
                ];
            })
            ->values()
            ->toArray();
    }
}
