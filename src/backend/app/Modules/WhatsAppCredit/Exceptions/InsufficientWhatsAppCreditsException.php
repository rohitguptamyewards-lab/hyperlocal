<?php

namespace App\Modules\WhatsAppCredit\Exceptions;

use RuntimeException;

/**
 * Thrown when a merchant has zero WhatsApp credits and enforcement is enabled.
 *
 * Callers must catch this and decide how to proceed:
 *  - ClaimService: skip WhatsApp silently (token still issued — user gets claim)
 *  - DispatchCampaignSends: mark remaining sends as failed, stop campaign
 *
 * NEVER propagate this to the HTTP response as a 500.
 * It is a business-rule exception, not a system error.
 */
class InsufficientWhatsAppCreditsException extends RuntimeException
{
    public function __construct(int $merchantId, int $balance)
    {
        parent::__construct(
            "Merchant {$merchantId} has insufficient WhatsApp credits (balance: {$balance})."
        );
    }
}
