<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a partnership transitions to LIVE status.
 *
 * Consumed by:
 *   - Enablement module: CreateEnablementRowsOnPartnershipLive
 *     (creates one partnership_staff_enablement row per outlet per participant)
 */
class PartnershipLive
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $partnershipId,
        public readonly int $triggeredByUserId,
    ) {}
}
