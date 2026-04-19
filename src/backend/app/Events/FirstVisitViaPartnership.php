<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when customer_type = NEW at redemption time.
 * Consumed by: Analytics module (create attribution record, start retention window).
 */
class FirstVisitViaPartnership
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int   $redemptionId,
        public readonly int   $partnershipId,
        public readonly int   $merchantId,
        public readonly int   $outletId,
        public readonly int   $customerType,
        public readonly ?int  $memberId,
    ) {}
}
