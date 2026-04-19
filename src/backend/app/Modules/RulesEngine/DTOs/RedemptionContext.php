<?php

namespace App\Modules\RulesEngine\DTOs;

use Carbon\Carbon;

/**
 * Input to RulesEngineService::evaluate().
 * Everything the engine needs to make a decision — no DB calls inside the DTO.
 */
readonly class RedemptionContext
{
    public function __construct(
        public int     $partnershipId,
        public int     $merchantId,
        public int     $outletId,
        public float   $billAmount,
        public string  $claimToken,
        public Carbon  $attemptedAt,
        public ?int    $customerId   = null,
        public ?string $customerPhone = null,
    ) {}
}
