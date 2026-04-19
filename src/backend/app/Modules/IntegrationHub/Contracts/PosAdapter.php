<?php

namespace App\Modules\IntegrationHub\Contracts;

/**
 * Contract for POS system connectors.
 * Implemented by: GenericPOSAdapter, (future) specific POS vendors.
 *
 * Used to push redemption confirmations back to the merchant's POS after a benefit is applied.
 */
interface PosAdapter
{
    /**
     * Push a redemption event to the POS system.
     *
     * @param  string $transactionId External bill/transaction reference from POS
     * @param  array  $payload       Redemption details (benefit_amount, member_phone, etc.)
     * @return bool                  True if POS acknowledged
     * @throws \RuntimeException     If POS is unreachable or rejects
     */
    public function pushRedemption(string $transactionId, array $payload): bool;
}
