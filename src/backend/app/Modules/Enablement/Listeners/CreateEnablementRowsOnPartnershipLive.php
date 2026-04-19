<?php

namespace App\Modules\Enablement\Listeners;

use App\Events\PartnershipLive;
use App\Models\Outlet;
use App\Modules\Enablement\Models\StaffEnablement;
use App\Modules\Partnership\Models\Partnership;

/**
 * Creates one partnership_staff_enablement row per outlet per participant
 * when a partnership transitions to LIVE status.
 *
 * Owner module: Enablement
 * Listens to:   PartnershipLive
 *
 * Brand-wide participants (outlet_id = NULL on participant row) get one row
 * per active outlet of their merchant.
 */
class CreateEnablementRowsOnPartnershipLive
{
    public function handle(PartnershipLive $event): void
    {
        $partnership = Partnership::with('participants')->find($event->partnershipId);

        if (!$partnership) {
            return;
        }

        foreach ($partnership->participants as $participant) {
            $outletIds = $this->resolveOutletIds($participant);

            foreach ($outletIds as $outletId) {
                StaffEnablement::firstOrCreate(
                    [
                        'merchant_id'    => $participant->merchant_id,
                        'partnership_id' => $partnership->id,
                        'outlet_id'      => $outletId,
                    ],
                    [
                        'is_dormant'          => false,
                        'dormancy_alert_sent' => false,
                        'created_by'          => $event->triggeredByUserId,
                        'updated_by'          => $event->triggeredByUserId,
                    ]
                );
            }
        }
    }

    /**
     * Resolve the outlet ID(s) for a participant.
     * Brand-wide (outlet_id = null) → all active outlets of that merchant.
     *
     * @return int[]
     */
    private function resolveOutletIds(object $participant): array
    {
        if ($participant->outlet_id !== null) {
            return [(int) $participant->outlet_id];
        }

        return Outlet::where('merchant_id', $participant->merchant_id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
