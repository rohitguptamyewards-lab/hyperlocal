<?php

namespace App\Modules\Ledger\Listeners;

use App\Events\RedemptionExecuted;
use App\Modules\Ledger\Models\LedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Listens to: RedemptionExecuted
 * Creates TWO ledger rows per redemption:
 *   1. BENEFIT_GIVEN   for the target merchant (they gave the discount)
 *   2. REFERRAL_CREDIT for the source merchant (they referred the customer)
 *
 * Owner module: Ledger
 * DO NOT write to partner_redemptions or partnerships from here.
 */
class CreateLedgerEntryOnRedemption
{
    public function handle(RedemptionExecuted $event): void
    {
        $redemption = DB::table('partner_redemptions as redemptions')
            ->leftJoin('partner_claims as claims', 'claims.id', '=', 'redemptions.claim_id')
            ->where('redemptions.id', $event->redemptionId)
            ->select([
                'redemptions.partnership_id',
                'redemptions.merchant_id as target_merchant_id',
                'redemptions.outlet_id as target_outlet_id',
                'redemptions.benefit_amount',
                'redemptions.created_at',
                'claims.merchant_id as source_merchant_id',
                'claims.source_outlet_id',
            ])
            ->first();

        if (!$redemption) {
            return;
        }

        $sourceMerchantId = $redemption->source_merchant_id;
        $sourceOutletId   = $redemption->source_outlet_id;
        $periodMonth      = Carbon::parse($redemption->created_at)->startOfMonth()->toDateString();

        $entries = [
            [
                'uuid'           => (string) Str::uuid(),
                'partnership_id' => $redemption->partnership_id,
                'redemption_id'  => $event->redemptionId,
                'merchant_id'    => $redemption->target_merchant_id, // target — gave benefit
                'outlet_id'      => $redemption->target_outlet_id,
                'entry_type'     => LedgerEntry::BENEFIT_GIVEN,
                'amount'         => $redemption->benefit_amount,
                'period_month'   => $periodMonth,
                'created_by'     => 0, // system-generated
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        // Only create referral_credit if we can resolve the source merchant
        if ($sourceMerchantId) {
            $entries[] = [
                'uuid'           => (string) Str::uuid(),
                'partnership_id' => $redemption->partnership_id,
                'redemption_id'  => $event->redemptionId,
                'merchant_id'    => $sourceMerchantId,         // source — sent the customer
                'outlet_id'      => $sourceOutletId ?? $redemption->target_outlet_id,
                'entry_type'     => LedgerEntry::REFERRAL_CREDIT,
                'amount'         => $redemption->benefit_amount,
                'period_month'   => $periodMonth,
                'created_by'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        foreach ($entries as $entry) {
            $exists = DB::table('partner_ledger_entries')
                ->where('redemption_id', $entry['redemption_id'])
                ->where('merchant_id', $entry['merchant_id'])
                ->where('entry_type', $entry['entry_type'])
                ->exists();

            if (!$exists) {
                DB::table('partner_ledger_entries')->insert($entry);
            }
        }
    }
}
