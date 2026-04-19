<?php

namespace App\Modules\LoyaltyBridge\Services;

use App\Modules\IntegrationHub\Adapters\LocalLoyaltyAdapter;
use App\Modules\IntegrationHub\Services\IntegrationResolverService;
use App\Modules\LoyaltyBridge\Models\MemberLoyaltyBalance;
use App\Modules\Member\Models\Member;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for all loyalty balance operations.
 *
 * Flow:
 *   getBalance  → check local cache → if stale, pull from external adapter → return
 *   award       → write to local balance → push to external adapter (fire-and-forget, logged on fail)
 *   deduct      → write to local balance → push to external adapter (fire-and-forget, logged on fail)
 *
 * Our ledger is the source of truth at runtime.
 * External providers are kept in sync as a best-effort push — failures are logged, not thrown.
 *
 * Owner module: LoyaltyBridge
 * Tables owned: member_loyalty_balances
 * Calls: IntegrationResolverService (adapter resolution)
 */
class LoyaltyBridgeService
{
    /** Re-fetch from external if local balance is older than this many minutes */
    private const STALE_MINUTES = 60;

    public function __construct(
        private readonly IntegrationResolverService $resolver,
        private readonly LocalLoyaltyAdapter        $local,
    ) {}

    /**
     * Get the current loyalty balance for a member at a merchant.
     * Returns local cache; refreshes from external if stale or missing.
     *
     * @param  string $phone
     * @param  int    $merchantId
     * @return float
     */
    public function getBalance(string $phone, int $merchantId): float
    {
        $member = Member::where('phone', $phone)->first();
        if (!$member) {
            return 0.0;
        }

        $row = MemberLoyaltyBalance::where('member_id', $member->id)
            ->where('merchant_id', $merchantId)
            ->first();

        $isStale = !$row
            || !$row->last_synced_at
            || $row->last_synced_at->diffInMinutes(now()) > self::STALE_MINUTES;

        if ($isStale) {
            try {
                $adapter  = $this->resolver->loyalty($merchantId);
                $external = $adapter->getBalance($phone, $merchantId);
                $this->writeLocalBalance($member->id, $merchantId, $external, $adapter instanceof LocalLoyaltyAdapter ? 'local' : 'external');
                return $external;
            } catch (\RuntimeException $e) {
                // External unavailable — fall back to local cache
                Log::warning('LoyaltyBridge: external balance fetch failed, using local cache.', [
                    'phone'       => $phone,
                    'merchant_id' => $merchantId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $row?->balance ?? 0.0;
    }

    /**
     * Award loyalty value to a member.
     * Updates local balance, then pushes to external (best-effort).
     *
     * @param  string $phone
     * @param  int    $merchantId
     * @param  float  $amount
     * @param  string $reference  Idempotency key
     * @return float              New balance after award
     * @throws \RuntimeException  If local write fails
     */
    public function award(string $phone, int $merchantId, float $amount, string $reference): float
    {
        // Always write local first
        $this->local->award($phone, $merchantId, $amount, $reference);

        // Push to external if configured — best-effort
        $externalAdapter = $this->resolver->loyalty($merchantId);
        if (!($externalAdapter instanceof LocalLoyaltyAdapter)) {
            try {
                $externalAdapter->award($phone, $merchantId, $amount, $reference);
            } catch (\RuntimeException $e) {
                Log::warning('LoyaltyBridge: external award push failed.', [
                    'phone'       => $phone,
                    'merchant_id' => $merchantId,
                    'amount'      => $amount,
                    'reference'   => $reference,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $this->getBalance($phone, $merchantId);
    }

    /**
     * Deduct loyalty value from a member.
     * Updates local balance, then pushes to external (best-effort).
     *
     * @param  string $phone
     * @param  int    $merchantId
     * @param  float  $amount
     * @param  string $reference  Idempotency key
     * @return float              New balance after deduction
     * @throws \RuntimeException  If insufficient local balance
     */
    public function deduct(string $phone, int $merchantId, float $amount, string $reference): float
    {
        $this->local->deduct($phone, $merchantId, $amount, $reference); // throws if insufficient

        $externalAdapter = $this->resolver->loyalty($merchantId);
        if (!($externalAdapter instanceof LocalLoyaltyAdapter)) {
            try {
                $externalAdapter->deduct($phone, $merchantId, $amount, $reference);
            } catch (\RuntimeException $e) {
                Log::warning('LoyaltyBridge: external deduct push failed.', [
                    'phone'       => $phone,
                    'merchant_id' => $merchantId,
                    'amount'      => $amount,
                    'reference'   => $reference,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $this->getBalance($phone, $merchantId);
    }

    // ─────────────────────────────────────────────────────────

    private function writeLocalBalance(int $memberId, int $merchantId, float $balance, string $provider): void
    {
        MemberLoyaltyBalance::updateOrCreate(
            ['member_id' => $memberId, 'merchant_id' => $merchantId],
            [
                'balance'        => $balance,
                'provider'       => $provider,
                'last_synced_at' => now(),
            ],
        );
    }
}
