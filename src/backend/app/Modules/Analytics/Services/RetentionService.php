<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PartnerAttribution;
use Illuminate\Support\Facades\DB;

/**
 * Updates retention flags on partner_attributions.
 * Called by UpdateRetentionFlags console command (scheduled daily).
 *
 * Logic: A customer is "retained" at 30/60/90 days if they made at least one
 * additional redemption at the TARGET merchant within that window.
 *
 * Owner module: Analytics
 * Reads: partner_attributions, partner_redemptions
 * Writes: partner_attributions (retained_Nd, retained_Nd_at columns only)
 */
class RetentionService
{
    /**
     * Update retention flags for attributions that hit their 30/60/90-day window today.
     * Only processes attributions with a known customer_id.
     */
    public function updateFlags(): array
    {
        $updated = ['30d' => 0, '60d' => 0, '90d' => 0];

        foreach ([30 => '30d', 60 => '60d', 90 => '90d'] as $days => $label) {
            $windowStart = now()->subDays($days + 1);
            $windowEnd   = now()->subDays($days - 1);

            // Find attributions whose window just matured (attributed_at ≈ N days ago)
            $attributions = PartnerAttribution::whereNotNull('customer_id')
                ->whereBetween('attributed_at', [$windowStart, $windowEnd])
                ->where("retained_{$label}", false)
                ->get();

            foreach ($attributions as $attribution) {
                $hasReturn = DB::table('partner_redemptions')
                    ->where('merchant_id', $attribution->target_merchant_id)
                    ->where('status', 1)
                    ->where(function ($query) use ($attribution): void {
                        $query->where('member_id', $attribution->customer_id)
                            ->orWhere('customer_id', $attribution->customer_id);
                    })
                    ->where('created_at', '>', $attribution->attributed_at)
                    ->where('created_at', '<=', $attribution->attributed_at->addDays($days))
                    ->exists();

                if ($hasReturn) {
                    $attribution->update([
                        "retained_{$label}"    => true,
                        "retained_{$label}_at" => now(),
                    ]);
                    $updated[$label]++;
                }
            }
        }

        return $updated;
    }
}
