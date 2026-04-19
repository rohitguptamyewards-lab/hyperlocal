<?php

namespace App\Modules\WhatsAppCredit\Listeners;

use App\Models\Merchant;
use App\Modules\WhatsAppCredit\Events\LowWhatsAppCreditEvent;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the super admin when a merchant's WhatsApp credit balance drops
 * to or below the low-balance threshold.
 *
 * The super admin dashboard already surfaces this via the merchants_low_credits
 * count in SuperAdminService::platformStats() — which reads the low_balance_alerted
 * flag set in WhatsAppCreditService before this event fires.
 *
 * This listener adds a structured log entry for monitoring and is the hook point
 * for a real-time SA notification feed when that is built.
 *
 * Owner module: WhatsAppCredit
 * Listens to: LowWhatsAppCreditEvent
 */
class NotifySuperAdminOnLowCredit
{
    public function handle(LowWhatsAppCreditEvent $event): void
    {
        $merchant = Merchant::find($event->merchantId);

        Log::warning('[LowWhatsAppCredit] SA alert — merchant below threshold. Dashboard will surface via merchants_low_credits count.', [
            'merchant_id'   => $event->merchantId,
            'merchant_name' => $merchant?->name,
            'balance'       => $event->currentBalance,
            'threshold'     => $event->threshold,
        ]);

        // Future: push to SA real-time notification feed when built.
    }
}
