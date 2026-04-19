<?php

namespace App\Modules\CustomerActivation\Services;

use App\Modules\WhatsAppCredit\Exceptions\InsufficientWhatsAppCreditsException;
use App\Modules\WhatsAppCredit\Services\WhatsAppCreditService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp notification service — injectable singleton.
 *
 * Converted from static class to injectable service so WhatsAppCreditService
 * can be injected and credits deducted per send.
 *
 * Driver selection via WHATSAPP_DRIVER env var:
 *   mock   → logs to storage/logs/laravel.log (default for local dev)
 *   msg91  → sends via MSG91 WhatsApp Business API
 *
 * Credit enforcement:
 *   - deduct() is called BEFORE each send
 *   - If balance = 0 and WHATSAPP_CREDIT_ENFORCEMENT=true → InsufficientWhatsAppCreditsException
 *   - If WHATSAPP_CREDIT_ENFORCEMENT=false (default) → sends proceed regardless of balance
 *
 * Callers must handle InsufficientWhatsAppCreditsException:
 *   - ClaimService: catch + skip (token still issued, no WhatsApp delivery)
 *   - DispatchCampaignSends: catch + mark sends failed, abort campaign send loop
 *
 * Owner module: CustomerActivation (platform-wide dependency)
 * Integration points: MSG91 WhatsApp Business API, WhatsAppCreditService
 */
class WhatsAppNotifier
{
    private const MSG91_API_URL = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/';

    public function __construct(
        private readonly WhatsAppCreditService $credits,
    ) {}

    /**
     * Send a claim token to the customer via WhatsApp.
     * Deducts one credit from the merchant's balance before sending.
     *
     * @param  string   $phone       E.164 format, e.g. +919876543210
     * @param  string   $token       Claim token, e.g. HLP4X9K2
     * @param  string   $expiresAt   Human-readable datetime string
     * @param  int|null $merchantId  Used for credit deduction; null = no deduction
     * @param  int|null $referenceId partner_claims.id for ledger traceability
     * @throws InsufficientWhatsAppCreditsException  When enforcement is on and balance = 0
     */
    public function send(
        string $phone,
        string $token,
        string $expiresAt,
        ?int   $merchantId = null,
        ?int   $referenceId = null,
    ): void {
        if ($merchantId !== null) {
            // Throws InsufficientWhatsAppCreditsException if balance = 0 and enforcement = on
            $this->credits->deduct($merchantId, 'partner_claim', $referenceId);
        }

        $driver = config('services.whatsapp.driver', 'mock');

        match ($driver) {
            'msg91' => $this->sendViaMSG91($phone, $token, $expiresAt),
            default => $this->mockSend($phone, $token, $expiresAt),
        };
    }

    /**
     * Send a raw pre-built message string — used by Campaign sends.
     * Deducts one credit per send.
     *
     * @param  string   $phone
     * @param  string   $message       Pre-built message content
     * @param  int|null $merchantId    Used for credit deduction
     * @param  int|null $referenceId   campaign_sends.id for ledger traceability
     * @throws InsufficientWhatsAppCreditsException  When enforcement is on and balance = 0
     */
    public function sendRaw(
        string $phone,
        string $message,
        ?int   $merchantId = null,
        ?int   $referenceId = null,
    ): void {
        if ($merchantId !== null) {
            $this->credits->deduct($merchantId, 'campaign_send', $referenceId);
        }

        $driver = config('services.whatsapp.driver', 'mock');

        if ($driver !== 'msg91') {
            Log::channel('stack')->info('[WhatsApp MOCK — Campaign]', [
                'to'      => $phone,
                'message' => $message,
            ]);
            return;
        }

        // In production: route to MSG91 with an appropriate approved template per campaign type
        Log::info('[WhatsApp Campaign — MSG91 send pending template approval]', [
            'to'      => $phone,
            'message' => $message,
        ]);
    }

    // ── MSG91 driver ─────────────────────────────────────────

    private function sendViaMSG91(string $phone, string $token, string $expiresAt): void
    {
        $authKey    = config('services.whatsapp.msg91_auth_key');
        $fromNumber = config('services.whatsapp.msg91_integrated_number');
        $templateId = config('services.whatsapp.msg91_template_id');

        if (!$authKey || !$fromNumber || !$templateId) {
            Log::warning('[WhatsApp MSG91] Missing configuration — falling back to mock.', [
                'to' => $phone,
            ]);
            $this->mockSend($phone, $token, $expiresAt);
            return;
        }

        $to = ltrim($phone, '+');

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'authkey'      => $authKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::MSG91_API_URL, [
                    'integrated_number' => $fromNumber,
                    'content_type'      => 'template',
                    'payload'           => [
                        'to'       => $to,
                        'type'     => 'template',
                        'template' => [
                            'name'       => $templateId,
                            'language'   => ['code' => 'en'],
                            'components' => [
                                [
                                    'type'       => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $token],
                                        ['type' => 'text', 'text' => $expiresAt],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('[WhatsApp MSG91] Send failed.', [
                    'to'     => $phone,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[WhatsApp MSG91] Exception.', [
                'to'      => $phone,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function mockSend(string $phone, string $token, string $expiresAt): void
    {
        Log::channel('stack')->info('[WhatsApp MOCK]', [
            'to'         => $phone,
            'message'    => "Your Hyperlocal offer code is: {$token}. Valid until {$expiresAt}.",
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);
    }
}
