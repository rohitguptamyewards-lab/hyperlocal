<?php

namespace App\Modules\Partnership\Listeners;

use App\Events\PartnershipCapExhausted;
use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Services\PartnershipService;
use Illuminate\Support\Facades\Log;

/**
 * Listens to: PartnershipCapExhausted
 * Automatically pauses the partnership when any cap is fully exhausted.
 *
 * Owner module: Partnership
 * Writes: partnerships.status → PAUSED
 *
 * Design decision: auto-pause is a system action; uses system user (id=0 placeholder).
 * The partnership can be manually resumed once the merchant reviews the situation.
 */
class AutoPauseOnCapExhausted
{
    public function __construct(private readonly PartnershipService $service) {}

    public function handle(PartnershipCapExhausted $event): void
    {
        $partnership = Partnership::find($event->partnershipId);

        if (!$partnership || $partnership->status !== PartnershipStatus::LIVE) {
            return; // Already paused, expired, or not found — nothing to do
        }

        if (!$partnership->canTransitionTo(PartnershipStatus::PAUSED)) {
            return;
        }

        $capLabel = match ($event->capType) {
            'monthly' => 'Monthly partnership cap',
            'outlet'  => 'Outlet monthly cap',
            default   => ucfirst($event->capType) . ' cap',
        };

        try {
            $partnership->update([
                'status'        => PartnershipStatus::PAUSED,
                'paused_at'     => now(),
                'paused_reason' => "{$capLabel} exhausted — auto-paused by system.",
                'updated_by'    => 0, // system action
            ]);

            Log::info('Partnership auto-paused: cap exhausted', [
                'partnership_id' => $event->partnershipId,
                'cap_type'       => $event->capType,
                'merchant_id'    => $event->merchantId,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal: log and continue. The cap is still enforced at the Rules Engine level.
            Log::error('AutoPauseOnCapExhausted failed', [
                'partnership_id' => $event->partnershipId,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
