<?php

namespace App\Modules\IntegrationHub\Contracts;

/**
 * Contract for loyalty balance providers.
 * Implemented by: LocalLoyaltyAdapter, EwardsAdapter, (future) CapillaryAdapter.
 *
 * All implementations must be idempotent on award/deduct using the reference string.
 * All implementations must throw \RuntimeException on provider failure — never return null.
 */
interface LoyaltyAdapter
{
    /**
     * Fetch the current loyalty balance for a member at a merchant.
     *
     * @param  string $phone      Normalised phone number
     * @param  int    $merchantId Our internal merchant ID
     * @return float              Balance in the provider's native unit (points, cashback, etc.)
     * @throws \RuntimeException  If provider is unreachable
     */
    public function getBalance(string $phone, int $merchantId): float;

    /**
     * Award loyalty value to a member.
     *
     * @param  string $phone      Normalised phone number
     * @param  int    $merchantId Our internal merchant ID
     * @param  float  $amount     Amount to award
     * @param  string $reference  Idempotency key (claim UUID, redemption UUID, etc.)
     * @return bool               True if awarded, false if already processed (idempotent)
     * @throws \RuntimeException  If provider rejects the award
     */
    public function award(string $phone, int $merchantId, float $amount, string $reference): bool;

    /**
     * Deduct loyalty value from a member.
     *
     * @param  string $phone      Normalised phone number
     * @param  int    $merchantId Our internal merchant ID
     * @param  float  $amount     Amount to deduct
     * @param  string $reference  Idempotency key
     * @return bool               True if deducted
     * @throws \RuntimeException  If insufficient balance or provider rejects
     */
    public function deduct(string $phone, int $merchantId, float $amount, string $reference): bool;

    /**
     * Retrieve a page of customers (phone numbers) for a merchant from the provider.
     * Used for member sync (SyncMembersFromIntegration job).
     *
     * @param  int $merchantId Our internal merchant ID
     * @param  int $page       1-based page number
     * @param  int $perPage    Page size (max 200)
     * @return array{
     *   data: array<int, array{phone: string, name: string|null, external_id: string|null}>,
     *   has_more: bool,
     *   total: int|null
     * }
     * @throws \RuntimeException If provider is unreachable
     */
    public function getCustomers(int $merchantId, int $page, int $perPage): array;
}
