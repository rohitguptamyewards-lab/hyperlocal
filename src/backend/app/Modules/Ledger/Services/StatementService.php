<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Generates monthly statements for a partnership or merchant.
 *
 * Owner module: Ledger
 * Reads: partner_ledger_entries
 * Writes: nothing
 */
class StatementService
{
    /**
     * Monthly statement for one partnership, from the requesting merchant's perspective.
     *
     * Returns an array of period rows, each with:
     *   period_month, benefit_given, referral_credits, redemption_count, net
     */
    public function forPartnership(int $partnershipId, int $merchantId, int $months = 6): array
    {
        $rows = DB::table('partner_ledger_entries')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->where('period_month', '>=', now()->subMonths($months - 1)->startOfMonth()->toDateString())
            ->selectRaw("
                strftime('%Y-%m', period_month) as period,
                SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END) as benefit_given,
                SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END) as referral_credits,
                COUNT(*) as entry_count
            ", [LedgerEntry::BENEFIT_GIVEN, LedgerEntry::REFERRAL_CREDIT])
            ->groupByRaw("strftime('%Y-%m', period_month)")
            ->orderByRaw("MIN(period_month)")
            ->get();

        return $rows->map(fn ($r) => [
            'period'           => $r->period,
            'benefit_given'    => (float) $r->benefit_given,
            'referral_credits' => (float) $r->referral_credits,
            'net'              => (float) $r->referral_credits - (float) $r->benefit_given,
        ])->values()->all();
    }

    /**
     * All-partnerships summary for a merchant — total given and received per month.
     */
    public function summaryForMerchant(int $merchantId, int $months = 6): array
    {
        $rows = DB::table('partner_ledger_entries')
            ->where('merchant_id', $merchantId)
            ->where('period_month', '>=', now()->subMonths($months - 1)->startOfMonth()->toDateString())
            ->selectRaw("
                strftime('%Y-%m', period_month) as period,
                SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END) as total_given,
                SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END) as total_received
            ", [LedgerEntry::BENEFIT_GIVEN, LedgerEntry::REFERRAL_CREDIT])
            ->groupByRaw("strftime('%Y-%m', period_month)")
            ->orderByRaw("MIN(period_month)")
            ->get();

        return $rows->map(fn ($r) => [
            'period'         => $r->period,
            'total_given'    => (float) $r->total_given,
            'total_received' => (float) $r->total_received,
            'net'            => (float) $r->total_received - (float) $r->total_given,
        ])->values()->all();
    }
}
