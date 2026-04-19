<?php

namespace App\Modules\WhatsAppCredit\Listeners;

use App\Models\Merchant;
use App\Modules\WhatsAppCredit\Events\LowWhatsAppCreditEvent;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the merchant's admin users when their WhatsApp credit balance
 * drops to or below the low-balance threshold.
 *
 * Current behaviour: logs the alert.
 * When a notification channel is configured (email / WhatsApp), replace the
 * TODO block below — the event payload has everything needed.
 *
 * Owner module: WhatsAppCredit
 * Listens to: LowWhatsAppCreditEvent
 */
class NotifyMerchantOnLowCredit
{
    public function handle(LowWhatsAppCreditEvent $event): void
    {
        $merchant = Merchant::find($event->merchantId);

        Log::warning('[LowWhatsAppCredit] Merchant balance below threshold — notification channel not yet configured.', [
            'merchant_id'   => $event->merchantId,
            'merchant_name' => $merchant?->name,
            'balance'       => $event->currentBalance,
            'threshold'     => $event->threshold,
        ]);

        // TODO: when email/WhatsApp notification channel is confirmed, send alert
        // to the merchant's admin users (role = 1):
        //
        // $adminUsers = $merchant?->users()->where('role', 1)->get() ?? collect();
        // foreach ($adminUsers as $user) {
        //     Mail::to($user->email)->queue(new LowCreditAlertMail($merchant, $event->currentBalance));
        // }
    }
}
