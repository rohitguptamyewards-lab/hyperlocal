<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after every successful redemption.
 * Consumed by: Ledger module (create ledger entry), Analytics module.
 */
class RedemptionExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int   $redemptionId,
        public readonly int   $partnershipId,
        public readonly int   $merchantId,
        public readonly int   $outletId,
        public readonly int   $customerType,
        public readonly float $benefitAmount,
        public readonly ?int  $memberId,
    ) {}
}
