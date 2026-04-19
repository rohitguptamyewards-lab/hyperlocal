<?php

namespace App\Modules\Execution\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Generates and validates manager approval codes for high-value redemptions.
 *
 * Purpose: When the RulesEngine sets requiresApproval=true, the cashier
 *          cannot proceed until a valid approval code has been presented.
 *
 * Design:
 *   - Codes are 6-digit numeric strings (100000–999999)
 *   - Stored in the application cache under a token+partnership composite key
 *   - TTL: 10 minutes from generation
 *   - Single-use: consumed on successful redeem
 *
 * In production, the code would be delivered to the manager's device via
 * WhatsApp or a push notification. In this standalone build it is returned
 * in the API response so the cashier UI can display it for demo purposes.
 *
 * Owner module: Execution
 * Integration points: RedemptionService (validates before writing)
 */
class ApprovalService
{
    public const CODE_TTL_SECONDS = 600; // 10 minutes

    /**
     * Generate a fresh approval code for a claim token.
     * Overwrites any existing code for the same token (re-request resets TTL).
     */
    public function generate(string $claimToken, int $partnershipId): string
    {
        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($claimToken, $partnershipId), $code, self::CODE_TTL_SECONDS);
        return $code;
    }

    /**
     * Validate a code without consuming it.
     * Returns true if the code matches what is stored in cache.
     */
    public function validate(string $claimToken, int $partnershipId, string $code): bool
    {
        $stored = Cache::get($this->cacheKey($claimToken, $partnershipId));
        return $stored !== null && hash_equals($stored, $code);
    }

    /**
     * Consume a code: validate it and then delete it so it cannot be reused.
     * Returns true if the code was valid and has been consumed.
     */
    public function consume(string $claimToken, int $partnershipId, string $code): bool
    {
        $key    = $this->cacheKey($claimToken, $partnershipId);
        $stored = Cache::get($key);

        if ($stored === null || !hash_equals($stored, $code)) {
            return false;
        }

        Cache::forget($key);
        return true;
    }

    private function cacheKey(string $claimToken, int $partnershipId): string
    {
        return "approval_code:{$partnershipId}:{$claimToken}";
    }
}
