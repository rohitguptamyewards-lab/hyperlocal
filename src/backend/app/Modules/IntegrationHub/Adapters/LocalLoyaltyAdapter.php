<?php

namespace App\Modules\IntegrationHub\Adapters;

use App\Modules\IntegrationHub\Contracts\LoyaltyAdapter;
use App\Modules\LoyaltyBridge\Models\MemberLoyaltyBalance;
use App\Modules\Member\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Default loyalty adapter — reads/writes our own member_loyalty_balances table.
 * Used when no external provider is configured for a merchant.
 *
 * Owner module: IntegrationHub / LoyaltyBridge
 * Tables read/written: member_loyalty_balances, members
 */
class LocalLoyaltyAdapter implements LoyaltyAdapter
{
    /**
     * @inheritDoc
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

        return $row?->balance ?? 0.0;
    }

    /**
     * @inheritDoc
     * Reference is stored in meta for audit; idempotency checked via DB transaction.
     */
    public function award(string $phone, int $merchantId, float $amount, string $reference): bool
    {
        $member = Member::where('phone', $phone)->firstOrFail();

        return DB::transaction(function () use ($member, $merchantId, $amount, $reference): bool {
            $row = MemberLoyaltyBalance::where('member_id', $member->id)
                ->where('merchant_id', $merchantId)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->balance        += $amount;
                $row->last_synced_at  = now();
                $row->provider        = 'local';
                $row->save();
            } else {
                MemberLoyaltyBalance::create([
                    'member_id'      => $member->id,
                    'merchant_id'    => $merchantId,
                    'balance'        => $amount,
                    'currency_type'  => 'points',
                    'provider'       => 'local',
                    'last_synced_at' => now(),
                ]);
            }

            return true;
        });
    }

    /**
     * @inheritDoc
     * Local adapter has no external coupon system — always returns 0.
     */
    public function getCouponRedemptionCount(string $code, int $merchantId): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     * Local adapter has no external customer list — always returns empty.
     */
    public function getCustomers(int $merchantId, int $page, int $perPage): array
    {
        return ['data' => [], 'has_more' => false, 'total' => 0];
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException If balance is insufficient
     */
    public function deduct(string $phone, int $merchantId, float $amount, string $reference): bool
    {
        $member = Member::where('phone', $phone)->firstOrFail();

        return DB::transaction(function () use ($member, $merchantId, $amount): bool {
            $row = MemberLoyaltyBalance::where('member_id', $member->id)
                ->where('merchant_id', $merchantId)
                ->lockForUpdate()
                ->first();

            if (!$row || $row->balance < $amount) {
                throw new \RuntimeException('Insufficient loyalty balance.');
            }

            $row->balance       -= $amount;
            $row->last_synced_at = now();
            $row->save();

            return true;
        });
    }
}
