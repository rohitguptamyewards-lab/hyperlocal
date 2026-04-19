<?php

namespace App\Modules\Webhook\Services;

use App\Modules\Webhook\Models\WebhookSigningKey;
use Illuminate\Support\Facades\Cache;

/**
 * Verifies HMAC-SHA256 signatures on inbound webhooks.
 *
 * Signature scheme (matches standard practice):
 *   Header: X-Signature: sha256=<hex_digest>
 *   Header: X-Timestamp: <unix_timestamp>
 *   Header: X-Nonce: <unique_string>  (optional — prevents replay within cache window)
 *   Signed payload: "<timestamp>.<raw_body>"
 *
 * Validation steps:
 *   1. Parse and verify timestamp within allowed window (TIMESTAMP_TOLERANCE_SECONDS)
 *   2. Reconstruct signature using active key for the source
 *   3. Compare signatures with hash_equals (constant-time)
 *   4. Deduplicate nonce via cache to block replay attacks
 *
 * Owner module: Webhook
 * Reads: webhook_signing_keys
 * Uses: Laravel Cache for nonce deduplication
 */
class WebhookSignatureService
{
    /** Max age (seconds) of a webhook request before it is rejected. */
    private const TIMESTAMP_TOLERANCE_SECONDS = 300; // 5 minutes

    /** Cache TTL for seen nonces — must be > TIMESTAMP_TOLERANCE_SECONDS. */
    private const NONCE_CACHE_TTL_SECONDS = 600; // 10 minutes

    /**
     * Verify an inbound webhook signature.
     *
     * @param  string      $source     Source identifier (e.g. 'ewrds')
     * @param  string      $rawBody    Raw request body string
     * @param  string      $signature  Value of X-Signature header (e.g. "sha256=abc123")
     * @param  string|null $timestamp  Value of X-Timestamp header
     * @param  string|null $nonce      Value of X-Nonce header (optional)
     * @return bool        true if valid
     * @throws \InvalidArgumentException  on malformed headers
     * @throws \RuntimeException          on no active key for source
     */
    public function verify(
        string  $source,
        string  $rawBody,
        string  $signature,
        ?string $timestamp = null,
        ?string $nonce = null,
    ): bool {
        // Step 1: timestamp freshness check
        if ($timestamp !== null) {
            $ts = (int) $timestamp;
            if ($ts <= 0) {
                throw new \InvalidArgumentException('Invalid X-Timestamp header.');
            }
            if (abs(time() - $ts) > self::TIMESTAMP_TOLERANCE_SECONDS) {
                return false; // stale or future-dated
            }
        }

        // Step 2: nonce replay check
        if ($nonce !== null) {
            $cacheKey = "webhook_nonce:{$source}:{$nonce}";
            if (Cache::has($cacheKey)) {
                return false; // replay detected
            }
        }

        // Step 3: parse signature
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $receivedHex = substr($signature, 7);

        // Step 4: load active key for this source
        $keyRecord = WebhookSigningKey::where('source', $source)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->first();

        if (!$keyRecord) {
            throw new \RuntimeException("No active signing key found for source: {$source}");
        }

        // Step 5: compute expected signature
        $signedPayload = $timestamp !== null
            ? "{$timestamp}.{$rawBody}"
            : $rawBody;

        $expectedHex = hash_hmac('sha256', $signedPayload, $keyRecord->getDecryptedSecret());

        // Step 6: constant-time comparison
        if (!hash_equals($expectedHex, $receivedHex)) {
            return false;
        }

        // Step 7: record nonce to prevent replay
        if ($nonce !== null) {
            $cacheKey = "webhook_nonce:{$source}:{$nonce}";
            Cache::put($cacheKey, 1, self::NONCE_CACHE_TTL_SECONDS);
        }

        return true;
    }

    /**
     * Register a new signing key for a source.
     * Generates a cryptographically random secret, stores encrypted.
     *
     * @param  string $source      Source identifier
     * @param  string $keyId       Human-readable key ID for rotation tracking
     * @param  int    $createdBy   SuperAdmin ID
     * @return array{key_id: string, secret_plain: string}  Plain secret shown ONCE
     */
    public function createKey(string $source, string $keyId, int $createdBy): array
    {
        $plainSecret = bin2hex(random_bytes(32)); // 64-char hex secret

        WebhookSigningKey::create([
            'source'     => $source,
            'key_id'     => $keyId,
            'secret'     => encrypt($plainSecret),
            'is_active'  => true,
            'created_by' => $createdBy,
        ]);

        return [
            'key_id'       => $keyId,
            'secret_plain' => $plainSecret, // displayed once — not stored plain
        ];
    }

    /**
     * Deactivate a key by key_id (key rotation).
     */
    public function deactivateKey(string $keyId): void
    {
        WebhookSigningKey::where('key_id', $keyId)->update(['is_active' => false]);
    }
}
