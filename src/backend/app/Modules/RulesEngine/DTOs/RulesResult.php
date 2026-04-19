<?php

namespace App\Modules\RulesEngine\DTOs;

use App\Modules\RulesEngine\Constants\RulesDenyReason;

/**
 * Output of RulesEngineService::evaluate().
 * Execution module reads this to decide whether to proceed.
 */
readonly class RulesResult
{
    public function __construct(
        public bool    $allowed,
        public int     $customerType,
        public float   $maxBenefitAmount,
        public bool    $requiresApproval,
        public ?string $reasonCode    = null,
        public ?string $reasonDisplay = null,
        public ?string $fallbackHelp  = null,
    ) {}

    public static function deny(string $reasonCode, string $fallbackHelp = 'Contact your outlet manager.'): self
    {
        return new self(
            allowed:          false,
            customerType:     0,
            maxBenefitAmount: 0.0,
            requiresApproval: false,
            reasonCode:       $reasonCode,
            reasonDisplay:    RulesDenyReason::display($reasonCode),
            fallbackHelp:     $fallbackHelp,
        );
    }

    public static function allow(
        int   $customerType,
        float $maxBenefitAmount,
        bool  $requiresApproval = false,
    ): self {
        return new self(
            allowed:          true,
            customerType:     $customerType,
            maxBenefitAmount: $maxBenefitAmount,
            requiresApproval: $requiresApproval,
        );
    }
}
