<?php

namespace App\Modules\WhatsAppCredit\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per merchant when their WhatsApp credit balance drops to or
 * below the configured low-balance threshold (default 50 credits).
 *
 * The `low_balance_alerted` flag in merchant_whatsapp_balance is set to true
 * BEFORE this event fires, so the super admin dashboard surfaces the merchant
 * immediately via the merchants_low_credits count — regardless of whether the
 * downstream notification channel is configured.
 *
 * The flag is reset to false when credits are topped up (allocate()).
 * This ensures the event fires again on the next depletion cycle.
 *
 * Owner module: WhatsAppCredit
 * Consumed by: NotifyMerchantOnLowCredit, NotifySuperAdminOnLowCredit
 */
class LowWhatsAppCreditEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $merchantId,
        public readonly int $currentBalance,
        public readonly int $threshold,
    ) {}
}
