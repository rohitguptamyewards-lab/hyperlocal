<?php

namespace App\Modules\CustomerActivation\Services;

use App\Modules\CustomerActivation\Services\WhatsAppNotifier;
use App\Modules\Member\Services\MemberService;
use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Models\PartnershipRules;
use App\Modules\Partnership\Models\PartnershipTerms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generates claim tokens and writes partner_claims rows.
 *
 * Token format (D-002 LOCKED): HLP + 5 uppercase alphanumeric = 8 chars
 * e.g. HLP4X9K2
 *
 * WhatsApp delivery: mocked locally via Laravel Log.
 * Replace WhatsAppNotifier::send() when eWards gateway spec arrives.
 *
 * Member identity: phone is resolved to a Member record via MemberService.
 * member_id is stored on partner_claims for future segment/analytics use.
 */
class ClaimService
{
    public function __construct(
        private readonly MemberService    $memberService,
        private readonly WhatsAppNotifier $whatsapp,
    ) {}
    private const TOKEN_PREFIX      = 'HLP';
    private const TOKEN_RANDOM_LEN  = 5;
    private const VALIDITY_HOURS    = 48;
    private const MAX_TOKEN_RETRIES = 5;

    /**
     * Issue a claim token for a customer at a source outlet.
     *
     * @throws ValidationException
     */
    public function issue(
        int     $partnershipId,
        int     $merchantId,       // issuing merchant (SOURCE side — the merchant whose customer is visiting)
        int     $sourceOutletId,   // where QR was scanned
        int     $targetOutletId,   // where benefit will be redeemed
        int     $issuedByUserId,   // authenticated user performing the claim issuance (for audit)
        ?int    $customerId   = null,
        ?string $customerPhone = null,
    ): array {
        // Verify partnership is LIVE
        $partnership = Partnership::where('id', $partnershipId)
            ->where('status', PartnershipStatus::LIVE)
            ->first();

        if (!$partnership) {
            throw ValidationException::withMessages([
                'partnership_id' => ['This partnership is not currently active.'],
            ]);
        }

        $sourceOutletMerchantId = DB::table('outlets')->where('id', $sourceOutletId)->value('merchant_id');
        $targetOutletMerchantId = DB::table('outlets')->where('id', $targetOutletId)->value('merchant_id');

        if ((int) $sourceOutletMerchantId !== (int) $merchantId) {
            throw ValidationException::withMessages([
                'source_outlet_id' => ['The selected source outlet does not belong to your brand.'],
            ]);
        }

        if (!$targetOutletMerchantId || (int) $targetOutletMerchantId === (int) $merchantId) {
            throw ValidationException::withMessages([
                'target_outlet_id' => ['The selected target outlet must belong to your partner brand.'],
            ]);
        }

        // Verify both participant merchants are still in the ecosystem (E-001)
        $inactiveParticipant = DB::table('partnership_participants')
            ->where('partnership_participants.partnership_id', $partnershipId)
            ->whereNull('partnership_participants.deleted_at')
            ->join('merchants', 'merchants.id', '=', 'partnership_participants.merchant_id')
            ->where('merchants.ecosystem_active', false)
            ->exists();

        if ($inactiveParticipant) {
            throw ValidationException::withMessages([
                'partnership_id' => ['This partnership is no longer active — one of the merchants is no longer in the network.'],
            ]);
        }

        // Verify the source merchant has issuing enabled
        $sourceParticipant = DB::table('partnership_participants')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->first();
        $targetParticipant = DB::table('partnership_participants')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $targetOutletMerchantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$sourceParticipant || !$targetParticipant) {
            throw ValidationException::withMessages([
                'partnership_id' => ['These outlets are not part of this partnership.'],
            ]);
        }

        if (!$this->isOutletInScope($partnershipId, $merchantId, $sourceOutletId)) {
            throw ValidationException::withMessages([
                'source_outlet_id' => ['This outlet is not enabled for claim issuance in this partnership.'],
            ]);
        }

        if (!$this->isOutletInScope($partnershipId, (int) $targetOutletMerchantId, $targetOutletId)) {
            throw ValidationException::withMessages([
                'target_outlet_id' => ['This outlet is not enabled for redemption in this partnership.'],
            ]);
        }

        if ($sourceParticipant && !$sourceParticipant->issuing_enabled) {
            throw ValidationException::withMessages([
                'partnership_id' => ['Issuing is currently disabled on your side of this partnership.'],
            ]);
        }

        if (!$targetParticipant->redemption_enabled) {
            throw ValidationException::withMessages([
                'partnership_id' => ['Your partner is not currently accepting redemptions for this partnership.'],
            ]);
        }

        // Check blackout rules and time bands at issuance time
        // (mirrors the enforcement already done at redemption in RulesEngineService)
        $rules = PartnershipRules::where('partnership_id', $partnershipId)->first();
        if ($rules) {
            $now     = now();
            $date    = $now->toDateString();
            $weekday = (int) $now->format('N'); // 1=Mon … 7=Sun

            if (!empty($rules->blackout_rules)) {
                foreach ($rules->blackout_rules as $rule) {
                    if ($rule['type'] === 'date' && $rule['value'] === $date) {
                        throw ValidationException::withMessages([
                            'partnership_id' => ['This offer is not valid today.'],
                        ]);
                    }
                    if ($rule['type'] === 'weekday' && in_array($weekday, (array) $rule['value'], true)) {
                        throw ValidationException::withMessages([
                            'partnership_id' => ['This offer is not active on this day of the week.'],
                        ]);
                    }
                }
            }

            if (!empty($rules->time_band_rules)) {
                $time   = $now->format('H:i');
                $inBand = false;
                foreach ($rules->time_band_rules as $band) {
                    if (in_array($weekday, (array) $band['days'], true)) {
                        if ($time >= $band['from'] && $time <= $band['to']) {
                            $inBand = true;
                            break;
                        }
                    }
                }
                if (!$inBand) {
                    throw ValidationException::withMessages([
                        'partnership_id' => ['This offer is only active during specific hours.'],
                    ]);
                }
            }
        }

        // Check daily transaction count
        $terms = PartnershipTerms::where('partnership_id', $partnershipId)->first();
        if ($terms?->daily_transaction_count !== null) {
            $todayCount = DB::table('partner_claims')
                ->where('partnership_id', $partnershipId)
                ->whereDate('created_at', today())
                ->whereIn('status', [1, 2])
                ->count();
            if ($todayCount >= $terms->daily_transaction_count) {
                throw ValidationException::withMessages([
                    'partnership_id' => ['Daily token limit for this partnership has been reached.'],
                ]);
            }
        }
        if ($terms?->outlet_daily_count !== null) {
            $outletTodayCount = DB::table('partner_claims')
                ->where('partnership_id', $partnershipId)
                ->where('source_outlet_id', $sourceOutletId)
                ->whereDate('created_at', today())
                ->whereIn('status', [1, 2])
                ->count();
            if ($outletTodayCount >= $terms->outlet_daily_count) {
                throw ValidationException::withMessages([
                    'partnership_id' => ['Daily token limit for this outlet has been reached.'],
                ]);
            }
        }

        // Resolve or create member identity from phone number
        $memberId = null;
        if ($customerPhone) {
            $normalisedPhone = $this->memberService->normalise($customerPhone);
            $member          = $this->memberService->findOrCreateByPhone($normalisedPhone);
            $memberId        = $member->id;
        }

        if ($memberId && $this->isKnownCustomerOfMerchant($memberId, (int) $targetOutletMerchantId)) {
            throw ValidationException::withMessages([
                'customer_phone' => ['This customer already belongs to the partner brand and cannot receive this partnership token.'],
            ]);
        }

        $token    = $this->generateUniqueToken();
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addHours(self::VALIDITY_HOURS);

        $claimId = DB::table('partner_claims')->insertGetId([
            'uuid'             => (string) Str::uuid(),
            'merchant_id'      => $merchantId,
            'partnership_id'   => $partnershipId,
            'source_outlet_id' => $sourceOutletId,
            'target_outlet_id' => $targetOutletId,
            'customer_id'      => $customerId,
            'member_id'        => $memberId,
            'customer_phone'   => $customerPhone,
            'token'            => $token,
            'status'           => 1, // issued
            'issued_at'        => $issuedAt,
            'expires_at'       => $expiresAt,
            'created_by'       => $issuedByUserId,
            'updated_by'       => $issuedByUserId,
            'created_at'       => $issuedAt,
            'updated_at'       => $issuedAt,
        ]);

        // Send WhatsApp — credit deducted per send; silently skipped if insufficient credits
        if ($customerPhone) {
            try {
                $this->whatsapp->send(
                    phone:       $customerPhone,
                    token:       $token,
                    expiresAt:   $expiresAt->toDateTimeString(),
                    merchantId:  $merchantId,
                    referenceId: $claimId,
                );
            } catch (\App\Modules\WhatsAppCredit\Exceptions\InsufficientWhatsAppCreditsException $e) {
                // Token is still issued — just no WhatsApp delivery.
                // Merchant will see "credits exhausted" in their dashboard.
            }
        }

        return [
            'claim_id'   => $claimId,
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'valid_hours' => self::VALIDITY_HOURS,
        ];
    }

    // -------------------------------------------------------------------------

    private function isOutletInScope(int $partnershipId, int $merchantId, int $outletId): bool
    {
        return DB::table('partnership_participants')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($outletId): void {
                $query->whereNull('outlet_id')
                    ->orWhere('outlet_id', $outletId);
            })
            ->exists();
    }

    private function isKnownCustomerOfMerchant(int $memberId, int $merchantId): bool
    {
        if (DB::table('member_loyalty_balances')
            ->where('member_id', $memberId)
            ->where('merchant_id', $merchantId)
            ->exists()) {
            return true;
        }

        if (DB::table('partner_redemptions')
            ->where('member_id', $memberId)
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->exists()) {
            return true;
        }

        return DB::table('partner_claims')
            ->where('member_id', $memberId)
            ->where('merchant_id', $merchantId)
            ->whereIn('status', [1, 2])
            ->exists();
    }

    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < self::MAX_TOKEN_RETRIES; $i++) {
            $token = self::TOKEN_PREFIX . strtoupper(Str::random(self::TOKEN_RANDOM_LEN));

            $exists = DB::table('partner_claims')
                ->where('token', $token)
                ->whereIn('status', [1, 2]) // issued or redeemed — avoid collision
                ->exists();

            if (!$exists) {
                return $token;
            }
        }

        // Extremely unlikely — fallback with longer suffix
        return self::TOKEN_PREFIX . strtoupper(Str::random(8));
    }
}
