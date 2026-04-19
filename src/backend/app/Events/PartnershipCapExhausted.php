<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a cap counter reaches its ceiling after a redemption.
 * Consumed by: merchant alert notification, auto-pause check.
 */
class PartnershipCapExhausted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $partnershipId,
        public readonly int    $merchantId,
        public readonly string $capType, // 'monthly' | 'outlet'
        public readonly ?int   $outletId,
        public readonly int    $periodYear,
        public readonly int    $periodMonth,
    ) {}
}
