<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a partnership outlet is detected as dormant (no redemptions for 14+ days).
 * Fired at most once per dormancy cycle (guard: dormancy_alert_sent flag).
 *
 * Consumed by:
 *   - Notification service (not yet built — event fires but has no listener in V1)
 */
class PartnershipDormant
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $enablementId,
        public readonly int $partnershipId,
        public readonly int $merchantId,
        public readonly int $outletId,
    ) {}
}
