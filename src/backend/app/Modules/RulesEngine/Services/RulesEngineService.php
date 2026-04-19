<?php

namespace App\Modules\RulesEngine\Services;

use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Models\PartnershipParticipant;
use App\Modules\RulesEngine\Constants\CustomerType;
use App\Modules\RulesEngine\Constants\RulesDenyReason;
use App\Modules\RulesEngine\DTOs\RedemptionContext;
use App\Modules\RulesEngine\DTOs\RulesResult;
use App\Modules\Partnership\Models\PartnershipTerms;
use App\Modules\Partnership\Models\PartnershipRules;

/**
 * Single entry point for all redemption eligibility evaluation.
 *
 * Execution module calls: $result = $engine->evaluate($context)
 * Never call DB writes from here — this is read-only evaluation.
 * Writes (cap increment) happen in CapEnforcementService::increment(),
 * called by Execution AFTER this returns allowed=true.
 *
 * Evaluation order (fail-fast, cheapest checks first):
 *  1. Token validity
 *  2. Partnership is LIVE
 *  3. Outlet is in scope
 *  4. Blackout / time band
 *  5. Min bill threshold
 *  6. Customer type rules
 *  7. Uses per customer / cooling period
 *  8. Cap headroom check (read-only)
 *  9. Approval mode
 * 10. Compute max benefit amount
 */
class RulesEngineService
{
    public function __construct(
        private readonly CustomerClassifier  $classifier,
        private readonly CapEnforcementService $caps,
    ) {}

    public function evaluate(RedemptionContext $ctx): RulesResult
    {
        // ── 1. Token validity ─────────────────────────────────
        // Claims are issued by the SOURCE merchant and presented at the TARGET merchant.
        // Do NOT filter by merchant_id here — the token is cross-merchant.
        // Validate that claim.target_outlet_id belongs to the cashier's merchant instead.
        $claim = \DB::table('partner_claims')
            ->where('token', $ctx->claimToken)
            ->where('partnership_id', $ctx->partnershipId)
            ->first();

        if (!$claim) {
            return RulesResult::deny(RulesDenyReason::TOKEN_INVALID);
        }

        // Verify the target outlet belongs to the cashier's merchant.
        // (Claims are cross-merchant: issued at source, redeemed at target.)
        $targetOutletBelongsToMerchant = \DB::table('outlets')
            ->where('id', $claim->target_outlet_id)
            ->where('merchant_id', $ctx->merchantId)
            ->exists();

        if (!$targetOutletBelongsToMerchant) {
            return RulesResult::deny(RulesDenyReason::TOKEN_INVALID);
        }

        // Verify the source outlet belongs to a participant merchant in this partnership.
        // Prevents a fabricated claim using an outlet from an unrelated merchant.
        // Note: source can be EITHER proposer or acceptor — the partnership is bilateral.
        $sourceOutletMerchantId = \DB::table('outlets')
            ->where('id', $claim->source_outlet_id)
            ->value('merchant_id');

        $sourceIsParticipant = \DB::table('partnership_participants')
            ->where('partnership_id', $ctx->partnershipId)
            ->where('merchant_id', $sourceOutletMerchantId)
            ->exists();

        if (!$sourceIsParticipant) {
            return RulesResult::deny(RulesDenyReason::TOKEN_INVALID);
        }

        if ($claim->status === 2) {
            return RulesResult::deny(RulesDenyReason::TOKEN_ALREADY_USED);
        }
        if ($claim->status !== 1 || now()->isAfter($claim->expires_at)) {
            return RulesResult::deny(RulesDenyReason::TOKEN_EXPIRED);
        }

        // ── 2. Partnership is LIVE ────────────────────────────
        $partnership = Partnership::with(['terms', 'rules', 'participants'])
            ->find($ctx->partnershipId);

        if (!$partnership || $partnership->status !== PartnershipStatus::LIVE) {
            return RulesResult::deny(RulesDenyReason::PARTNERSHIP_NOT_LIVE);
        }

        // ── 2b. Ecosystem check (E-001) ───────────────────────
        // Both participant merchants must still be in the eWards ecosystem.
        $participantMerchantIds = $partnership->participants->pluck('merchant_id')->unique();
        $inactiveCount = \DB::table('merchants')
            ->whereIn('id', $participantMerchantIds)
            ->where('ecosystem_active', false)
            ->count();

        if ($inactiveCount > 0) {
            return RulesResult::deny(RulesDenyReason::ECOSYSTEM_INACTIVE);
        }

        // ── 3. Outlet in scope ────────────────────────────────
        $inScope = $partnership->participants
            ->where('merchant_id', $ctx->merchantId)
            ->filter(fn ($p) => $p->outlet_id === null || $p->outlet_id === $ctx->outletId)
            ->isNotEmpty();

        if (!$inScope) {
            return RulesResult::deny(RulesDenyReason::OUTLET_NOT_IN_SCOPE);
        }

        $terms = $partnership->terms;
        $rules = $partnership->rules;

        // ── 4. Blackout / time band ───────────────────────────
        if ($rules) {
            if ($denyReason = $this->checkBlackout($rules, $ctx)) {
                return RulesResult::deny($denyReason);
            }
            if ($denyReason = $this->checkTimeBand($rules, $ctx)) {
                return RulesResult::deny($denyReason);
            }
        }

        // ── 5. Min bill threshold ─────────────────────────────
        if ($terms?->min_bill_amount && $ctx->billAmount < $terms->min_bill_amount) {
            return RulesResult::deny(
                RulesDenyReason::MIN_BILL_NOT_MET,
                "Minimum bill of ₹{$terms->min_bill_amount} required."
            );
        }

        // ── 6. Customer type classification ───────────────────
        // Use member_id from the claim (set when customer submitted phone at QR scan).
        // Fall back to ctx->customerId if provided, then null (anonymous = NEW).
        $resolvedCustomerId = $claim->member_id ?? $ctx->customerId ?? null;
        $inactivityDays     = $rules?->inactivity_days ?? 90;
        $customerType       = $this->classifier->classify(
            $resolvedCustomerId,
            $ctx->merchantId,
            $ctx->outletId,
            $inactivityDays,
            $ctx->attemptedAt,
        );

        // Apply customer-type-specific rules
        if ($rules?->customer_type_rules) {
            $typeKey  = match ($customerType) {
                CustomerType::NEW         => 'new',
                CustomerType::EXISTING    => 'existing',
                CustomerType::REACTIVATED => 'reactivated',
                default                   => null,
            };

            $typeRules = $rules->customer_type_rules[$typeKey] ?? null;

            // If a type has cap_multiplier = 0 it means the offer is blocked for that type
            if ($typeRules !== null && ($typeRules['cap_multiplier'] ?? 1) === 0) {
                return RulesResult::deny(
                    RulesDenyReason::FIRST_TIME_ONLY,
                    'This offer is not available for your customer type.'
                );
            }
        }

        // ── 7. First-time-only check ──────────────────────────
        if ($rules?->first_time_only && $customerType !== CustomerType::NEW) {
            return RulesResult::deny(RulesDenyReason::FIRST_TIME_ONLY);
        }

        // ── 7b. Uses per customer / cooling period ────────────
        if ($resolvedCustomerId && $rules) {
            if ($denyReason = $this->checkCustomerUsage($rules, $ctx, $resolvedCustomerId)) {
                return RulesResult::deny($denyReason);
            }
        }

        // ── 8. Compute benefit amount ─────────────────────────
        $maxBenefit = $this->computeBenefitAmount($terms, $rules, $ctx->billAmount, $customerType);

        // ── 9. Cap headroom (read-only) ───────────────────────
        if ($terms) {
            $capDeny = $this->caps->check(
                $terms,
                $ctx->merchantId,
                $ctx->partnershipId,
                $ctx->outletId,
                $maxBenefit,
                $ctx->attemptedAt,
            );

            if ($capDeny !== null) {
                return $capDeny;
            }
        }

        // ── 10. Approval required? ────────────────────────────
        $requiresApproval = false;
        if ($terms?->approval_mode === 2) {
            $requiresApproval = true; // always requires manager
        } elseif ($terms?->approval_mode === 3) {
            $requiresApproval = true; // always requires OTP
        } elseif ($terms?->approval_threshold !== null) {
            $requiresApproval = $maxBenefit > $terms->approval_threshold;
        }

        return RulesResult::allow($customerType, $maxBenefit, $requiresApproval);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function checkBlackout(PartnershipRules $rules, RedemptionContext $ctx): ?string
    {
        if (empty($rules->blackout_rules)) {
            return null;
        }

        $date    = $ctx->attemptedAt->toDateString();           // e.g. 2026-12-25
        $weekday = (int) $ctx->attemptedAt->format('N');        // 1=Mon … 7=Sun

        foreach ($rules->blackout_rules as $rule) {
            if ($rule['type'] === 'date' && $rule['value'] === $date) {
                return RulesDenyReason::BLACKOUT_DATE;
            }
            if ($rule['type'] === 'weekday' && in_array($weekday, (array) $rule['value'], true)) {
                return RulesDenyReason::BLACKOUT_DATE;
            }
        }

        return null;
    }

    private function checkTimeBand(PartnershipRules $rules, RedemptionContext $ctx): ?string
    {
        if (empty($rules->time_band_rules)) {
            return null;
        }

        $weekday = (int) $ctx->attemptedAt->format('N');
        $time    = $ctx->attemptedAt->format('H:i');

        foreach ($rules->time_band_rules as $band) {
            $allowedDays = (array) $band['days'];
            if (in_array($weekday, $allowedDays, true)) {
                // This band applies to today — check time window
                if ($time >= $band['from'] && $time <= $band['to']) {
                    return null; // within allowed band
                }
                return RulesDenyReason::OUTSIDE_TIME_BAND;
            }
        }

        return null; // no band configured for today = unrestricted
    }

    private function checkCustomerUsage(PartnershipRules $rules, RedemptionContext $ctx, int $resolvedCustomerId): ?string
    {
        // Query by member_id (new system) — customer_id was always NULL before Member module was added.
        $pastRedemptions = \DB::table('partner_redemptions')
            ->where('partnership_id', $ctx->partnershipId)
            ->where('member_id', $resolvedCustomerId)
            ->where('status', 1) // completed
            ->orderByDesc('created_at')
            ->get(['created_at']);

        // Uses per customer
        if ($rules->uses_per_customer !== null && $pastRedemptions->count() >= $rules->uses_per_customer) {
            return RulesDenyReason::USES_LIMIT_REACHED;
        }

        // Cooling period
        if ($rules->cooling_period_days !== null && $pastRedemptions->isNotEmpty()) {
            $lastUsed    = \Carbon\Carbon::parse($pastRedemptions->first()->created_at);
            $daysSinceLast = $lastUsed->diffInDays($ctx->attemptedAt);

            if ($daysSinceLast < $rules->cooling_period_days) {
                return RulesDenyReason::COOLING_PERIOD_ACTIVE;
            }
        }

        return null;
    }

    private function computeBenefitAmount(
        ?PartnershipTerms $terms,
        ?PartnershipRules $rules,
        float             $billAmount,
        int               $customerType,
    ): float {
        if (!$terms) {
            return 0.0;
        }

        // Start from the bill-level cap
        $benefit = $billAmount; // no cap = full bill (edge case; terms should always have a cap)

        if ($terms->per_bill_cap_amount !== null && $terms->per_bill_cap_percent !== null) {
            // Both set — take the lower of the two
            $byAmount  = $terms->per_bill_cap_amount;
            $byPercent = round($billAmount * ($terms->per_bill_cap_percent / 100), 2);
            $benefit   = min($byAmount, $byPercent);
        } elseif ($terms->per_bill_cap_amount !== null) {
            $benefit = min($billAmount, $terms->per_bill_cap_amount);
        } elseif ($terms->per_bill_cap_percent !== null) {
            $benefit = round($billAmount * ($terms->per_bill_cap_percent / 100), 2);
        }

        // Outlet-level per-bill cap — a tighter ceiling for this specific outlet
        // Applied after the brand-level per-bill cap, taking the lower of the two.
        if ($terms->outlet_per_bill_cap_amount !== null) {
            $benefit = min($benefit, $terms->outlet_per_bill_cap_amount);
        }

        // Apply customer-type multiplier if configured
        if ($rules?->customer_type_rules) {
            $typeKey    = match ($customerType) {
                CustomerType::NEW         => 'new',
                CustomerType::EXISTING    => 'existing',
                CustomerType::REACTIVATED => 'reactivated',
                default                   => null,
            };
            $multiplier = $rules->customer_type_rules[$typeKey]['cap_multiplier'] ?? 1.0;
            $benefit    = round($benefit * (float) $multiplier, 2);
        }

        return max(0.0, $benefit);
    }
}
