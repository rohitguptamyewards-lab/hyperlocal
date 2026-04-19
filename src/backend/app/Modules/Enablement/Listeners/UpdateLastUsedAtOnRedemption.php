<?php

namespace App\Modules\Enablement\Listeners;

use App\Events\RedemptionExecuted;
use App\Modules\Enablement\Models\StaffEnablement;

/**
 * Updates last_used_at on the staff enablement row when a redemption occurs.
 * Also resets dormancy flags if the outlet had been dormant.
 *
 * Owner module: Enablement
 * Listens to:   RedemptionExecuted
 */
class UpdateLastUsedAtOnRedemption
{
    public function handle(RedemptionExecuted $event): void
    {
        $row = StaffEnablement::where('partnership_id', $event->partnershipId)
            ->where('merchant_id', $event->merchantId)
            ->where('outlet_id', $event->outletId)
            ->first();

        if (!$row) {
            return; // Enablement row may not exist yet (e.g. pre-migration data)
        }

        $update = ['last_used_at' => now()];

        // If outlet was dormant, a new redemption means it has recovered
        if ($row->is_dormant) {
            $update['is_dormant']          = false;
            $update['dormant_since']       = null;
            $update['dormancy_alert_sent'] = false;
        }

        $row->update($update);
    }
}
