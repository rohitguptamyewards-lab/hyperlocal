<?php

namespace App\Modules\WhatsAppCredit\Services;

use App\Modules\WhatsAppCredit\Events\LowWhatsAppCreditEvent;
use App\Modules\WhatsAppCredit\Exceptions\InsufficientWhatsAppCreditsException;
use App\Modules\WhatsAppCredit\Models\MerchantWhatsAppBalance;
use App\Modules\WhatsAppCredit\Models\WhatsAppCreditLedger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp credit lifecycle: allocate, deduct, balance check, ledger query.
 *
 * CONCURRENCY: All deductions use SELECT FOR UPDATE inside a DB transaction.
 * Never call getBalance() outside a locked transaction to make a credit decision.
 *
 * Feature flag: WHATSAPP_CREDIT_ENFORCEMENT (bool, default false).
 * When false: deductions are logged but not gated — no InsufficientWhatsAppCreditsException thrown.
 * Flip to true after super admin has pre-loaded credits for all active merchants.
 *
 * Owner module: WhatsAppCredit
 * Tables owned: merchant_whatsapp_balance, whatsapp_credit_ledger
 */
class WhatsAppCreditService
{
    private const CREDITS_PER_MESSAGE = 1;

    /**
     * Allocate WhatsApp credits to a merchant.
     * Called by super admin only.
     *
     * @param  int    $merchantId
     * @param  int    $amount        Positive integer — credits to add
     * @param  int    $allocatedBy   super_admins.id
     * @param  string $note          Optional note for the ledger
     * @return int                   New balance after allocation
     */
    public function allocate(int $merchantId, int $amount, int $allocatedBy, string $note = ''): int
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive.');
        }

        return DB::transaction(function () use ($merchantId, $amount, $allocatedBy, $note): int {
            $balance = MerchantWhatsAppBalance::where('merchant_id', $merchantId)
                ->lockForUpdate()
                ->first();

            $newBalance = ($balance?->balance ?? 0) + $amount;

            MerchantWhatsAppBalance::updateOrCreate(
                ['merchant_id' => $merchantId],
                [
                    'balance'             => $newBalance,
                    'low_balance_alerted' => false, // reset alert flag on top-up
                    'updated_at'          => now(),
                ]
            );

            WhatsAppCreditLedger::create([
                'merchant_id'    => $merchantId,
                'entry_type'     => 'allocation',
                'credits_delta'  => $amount,
                'balance_after'  => $newBalance,
                'reference_type' => 'manual',
                'note'           => $note ?: "Allocated by super admin",
                'allocated_by'   => $allocatedBy,
            ]);

            Log::info('[WhatsAppCredit] Allocated.', [
                'merchant_id' => $merchantId,
                'amount'      => $amount,
                'new_balance' => $newBalance,
                'by'          => $allocatedBy,
            ]);

            return $newBalance;
        });
    }

    /**
     * Deduct one credit for a WhatsApp message send.
     *
     * @param  int         $merchantId
     * @param  string      $referenceType   'partner_claim' | 'campaign_send' | 'approval_code'
     * @param  int|null    $referenceId     The causative row's id
     * @throws InsufficientWhatsAppCreditsException  When balance is 0 AND enforcement is on
     */
    public function deduct(int $merchantId, string $referenceType = 'manual', ?int $referenceId = null): void
    {
        $enforcement = (bool) config('services.whatsapp_credits.enforcement', false);

        DB::transaction(function () use ($merchantId, $referenceType, $referenceId, $enforcement): void {
            $balance = MerchantWhatsAppBalance::where('merchant_id', $merchantId)
                ->lockForUpdate()
                ->first();

            $current = $balance?->balance ?? 0;

            if ($current < self::CREDITS_PER_MESSAGE) {
                Log::warning('[WhatsAppCredit] Zero balance — message not sent.', [
                    'merchant_id'    => $merchantId,
                    'reference_type' => $referenceType,
                    'reference_id'   => $referenceId,
                    'enforcement'    => $enforcement,
                ]);

                if ($enforcement) {
                    throw new InsufficientWhatsAppCreditsException($merchantId, $current);
                }

                // Enforcement off — log and allow but do not write a ledger row
                return;
            }

            $newBalance = $current - self::CREDITS_PER_MESSAGE;

            MerchantWhatsAppBalance::where('merchant_id', $merchantId)->update([
                'balance'    => $newBalance,
                'updated_at' => now(),
            ]);

            WhatsAppCreditLedger::create([
                'merchant_id'    => $merchantId,
                'entry_type'     => 'consumption',
                'credits_delta'  => -self::CREDITS_PER_MESSAGE,
                'balance_after'  => $newBalance,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
            ]);

            // Check low-balance threshold and alert once
            $threshold = (int) config('services.whatsapp_credits.low_balance_threshold', 50);
            if ($newBalance <= $threshold && !($balance->low_balance_alerted ?? false)) {
                MerchantWhatsAppBalance::where('merchant_id', $merchantId)
                    ->update(['low_balance_alerted' => true]);

                Log::warning('[WhatsAppCredit] Low balance alert.', [
                    'merchant_id' => $merchantId,
                    'balance'     => $newBalance,
                    'threshold'   => $threshold,
                ]);
                LowWhatsAppCreditEvent::dispatch($merchantId, $newBalance, $threshold);
            }
        });
    }

    /**
     * Get current balance (read-only, no lock).
     * Use for display only — never use this to gate a send decision.
     */
    public function getBalance(int $merchantId): int
    {
        return MerchantWhatsAppBalance::where('merchant_id', $merchantId)
            ->value('balance') ?? 0;
    }

    /**
     * Paginated credit ledger for one merchant (newest first).
     */
    public function getLedger(int $merchantId, int $perPage = 50): LengthAwarePaginator
    {
        return WhatsAppCreditLedger::where('merchant_id', $merchantId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
