<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PartnerAttribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds missing partner_attributions rows from completed redemptions.
 *
 * Historical redemptions created before the attribution listener fix never
 * wrote analytics rows. This service fills only the missing rows and is
 * safe to run repeatedly.
 */
class AttributionBackfillService
{
    public function countMissing(?int $partnershipId = null): int
    {
        return $this->missingRowsQuery($partnershipId)->count();
    }

    public function backfill(?int $partnershipId = null): int
    {
        $inserted = 0;

        $this->missingRowsQuery($partnershipId)
            ->orderBy('redemptions.id')
            ->get()
            ->each(function ($row) use (&$inserted): void {
                $attributedAt = Carbon::parse($row->created_at);

                PartnerAttribution::updateOrCreate(
                    ['redemption_id' => $row->redemption_id],
                    [
                        'partnership_id'     => $row->partnership_id,
                        'customer_id'        => $row->member_id ?? $row->customer_id,
                        'source_merchant_id' => $row->source_merchant_id,
                        'target_merchant_id' => $row->target_merchant_id,
                        'outlet_id'          => $row->outlet_id,
                        'customer_type'      => $row->customer_type,
                        'benefit_amount'     => $row->benefit_amount,
                        'attributed_at'      => $attributedAt,
                        'period_month'       => $attributedAt->copy()->startOfMonth()->toDateString(),
                    ],
                );

                $inserted++;
            });

        return $inserted;
    }

    private function missingRowsQuery(?int $partnershipId = null)
    {
        $query = DB::table('partner_redemptions as redemptions')
            ->leftJoin('partner_attributions as attributions', 'attributions.redemption_id', '=', 'redemptions.id')
            ->leftJoin('partner_claims as claims', 'claims.id', '=', 'redemptions.claim_id')
            ->whereNull('attributions.id')
            ->where('redemptions.status', 1)
            ->whereNotNull('claims.merchant_id')
            ->select([
                'redemptions.id as redemption_id',
                'redemptions.partnership_id',
                'redemptions.member_id',
                'redemptions.customer_id',
                'redemptions.merchant_id as target_merchant_id',
                'redemptions.outlet_id',
                'redemptions.customer_type',
                'redemptions.benefit_amount',
                'redemptions.created_at',
                'claims.merchant_id as source_merchant_id',
            ]);

        if ($partnershipId !== null) {
            $query->where('redemptions.partnership_id', $partnershipId);
        }

        return $query;
    }
}
