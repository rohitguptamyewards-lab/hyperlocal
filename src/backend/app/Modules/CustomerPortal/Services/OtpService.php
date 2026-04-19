<?php

namespace App\Modules\CustomerPortal\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OTP generation, storage, and verification for customer portal.
 *
 * OTPs are stored in cache with a 5-minute TTL.
 * Rate limited: max 3 OTP requests per phone per 10 minutes.
 *
 * Owner module: CustomerPortal
 * Reads: cache
 * Writes: cache
 */
class OtpService
{
    private const OTP_TTL_MINUTES = 5;
    private const OTP_LENGTH = 6;

    private function rateLimitMax(): int
    {
        return (int) config('services.otp.rate_limit_max', 3);
    }

    private function rateLimitMinutes(): int
    {
        return (int) config('services.otp.rate_limit_minutes', 10);
    }

    /**
     * Generate and store a new OTP for the given phone number.
     *
     * @param  string $phone Normalised phone number
     * @return string The plain-text OTP (to send via WhatsApp)
     * @throws \RuntimeException If rate limit exceeded
     */
    public function generate(string $phone): string
    {
        $this->checkRateLimit($phone);

        $otp = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        Cache::put(
            $this->otpKey($phone),
            Hash::make($otp),
            now()->addMinutes(self::OTP_TTL_MINUTES),
        );

        $this->incrementRateLimit($phone);

        Log::info('CustomerPortal: OTP generated', ['phone' => $phone]);

        return $otp;
    }

    /**
     * Verify an OTP for the given phone number.
     * Deletes the OTP from cache on success (one-time use).
     *
     * @param  string $phone
     * @param  string $otp
     * @return bool
     */
    public function verify(string $phone, string $otp): bool
    {
        $hashed = Cache::get($this->otpKey($phone));

        if (!$hashed) {
            return false;
        }

        if (!Hash::check($otp, $hashed)) {
            return false;
        }

        Cache::forget($this->otpKey($phone));

        return true;
    }

    /**
     * Generate a session token for a verified customer.
     * Stored in cache with 24-hour TTL.
     *
     * @param  int $memberId
     * @return string Plain-text session token
     */
    public function createSessionToken(int $memberId): string
    {
        $token = Str::random(64);

        Cache::put(
            $this->sessionKey($token),
            $memberId,
            now()->addHours(24),
        );

        return $token;
    }

    /**
     * Resolve a session token to a member ID.
     *
     * @param  string $token
     * @return int|null
     */
    public function resolveSession(string $token): ?int
    {
        return Cache::get($this->sessionKey($token));
    }

    private function otpKey(string $phone): string
    {
        return "customer_otp:{$phone}";
    }

    private function sessionKey(string $token): string
    {
        return "customer_session:{$token}";
    }

    private function checkRateLimit(string $phone): void
    {
        $key = "customer_otp_rate:{$phone}";
        $count = (int) Cache::get($key, 0);

        if ($count >= $this->rateLimitMax()) {
            throw new \RuntimeException('Too many OTP requests. Please wait and try again.');
        }
    }

    private function incrementRateLimit(string $phone): void
    {
        $key = "customer_otp_rate:{$phone}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinutes($this->rateLimitMinutes()));
    }
}
