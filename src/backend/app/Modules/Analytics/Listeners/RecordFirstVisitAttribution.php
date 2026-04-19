<?php

namespace App\Modules\Analytics\Listeners;

use App\Events\RedemptionExecuted;
use App\Modules\Analytics\Models\PartnerAttribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Listens to: RedemptionExecuted
 * Creates one attribution row for EVERY redemption (not just new customers).
 * The customer_type column distinguishes new/existing/reactivated for ROI analysis.
 *
 * Owner module: Analytics
 * DO NOT write to any other table from here.
 */
class RecordFirstVisitAttribution
{
    public function handle(RedemptionExecuted $event): void
    {
        $redemption = DB::table('partner_redemptions as redemptions')
            ->leftJoin('partner_claims as claims', 'claims.id', '=', 'redemptions.claim_id')
            ->where('redemptions.id', $event->redemptionId)
            ->select([
                'redemptions.partnership_id',
                'redemptions.member_id',
                'redemptions.customer_id',
                'redemptions.merchant_id as target_merchant_id',
                'redemptions.outlet_id',
                'redemptions.customer_type',
                'redemptions.benefit_amount',
                'redemptions.created_at',
                'claims.merchant_id as source_merchant_id',
                'claims.source_outlet_id',
            ])
            ->first();

        if (!$redemption) {
            return;
        }

        $sourceMerchantId = $redemption->source_merchant_id
            ?? DB::table('outlets')->where('id', $redemption->source_outlet_id)->value('merchant_id');

        if (!$sourceMerchantId) {
            return;
        }

        $attributedAt = Carbon::parse($redemption->created_at);

        PartnerAttribution::updateOrCreate(
            ['redemption_id' => $event->redemptionId],
            [
                'partnership_id'     => $redemption->partnership_id,
                'customer_id'        => $redemption->member_id ?? $redemption->customer_id,
                'source_merchant_id' => $sourceMerchantId,
                'target_merchant_id' => $redemption->target_merchant_id,
                'outlet_id'          => $redemption->outlet_id,
                'customer_type'      => $redemption->customer_type,
                'benefit_amount'     => $redemption->benefit_amount,
                'attributed_at'      => $attributedAt,
                'period_month'       => $attributedAt->copy()->startOfMonth()->toDateString(),
            ],
        );
    }
}
