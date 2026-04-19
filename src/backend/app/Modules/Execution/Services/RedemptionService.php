<?php

namespace App\Modules\Execution\Services;

use App\Events\FirstVisitViaPartnership;
use App\Events\PartnershipCapExhausted;
use App\Events\RedemptionExecuted;
use App\Models\User;
use App\Modules\Execution\Services\ApprovalService;
use App\Modules\Partnership\Models\PartnershipTerms;
use App\Modules\RulesEngine\Constants\CustomerType;
use App\Modules\RulesEngine\DTOs\RedemptionContext;
use App\Modules\RulesEngine\DTOs\RulesResult;
use App\Modules\RulesEngine\Models\PartnershipRuleVersion;
use App\Modules\RulesEngine\Services\CapEnforcementService;
use App\Modules\RulesEngine\Services\RulesEngineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Executes a redemption end-to-end.
 *
 * Flow:
 *  1. Build RedemptionContext from request data
 *  2. Call RulesEngine::evaluate() — read-only eligibility check
 *  3. If denied → throw ValidationException immediately
 *  4. If allowed → open DB transaction:
 *       a. Insert partner_redemptions with rule_snapshot
 *       b. Mark partner_claims.status = 2 (REDEEMED)
 *       c. CapEnforcementService::increment() — locked atomic write
 *       d. Commit
 *  5. Dispatch events outside transaction
 *
 * Idempotency: UNIQUE(merchant_id, transaction_id) on partner_redemptions.
 * Duplicate submission returns the existing record.
 */
class RedemptionService
{
    public function __construct(
        private readonly RulesEngineService    $engine,
        private readonly CapEnforcementService $caps,
        private readonly ApprovalService       $approval,
    ) {}

    /**
     * Evaluate eligibility without executing. Used for cashier pre-check.
     */
    public function evaluate(
        int    $partnershipId,
        int    $merchantId,
        int    $outletId,
        float  $billAmount,
        string $claimToken,
        ?int   $customerId = null,
    ): RulesResult {
        return $this->engine->evaluate(new RedemptionContext(
            partnershipId: $partnershipId,
            merchantId:    $merchantId,
            outletId:      $outletId,
            billAmount:    $billAmount,
            claimToken:    $claimToken,
            attemptedAt:   now(),
            customerId:    $customerId,
        ));
    }

    /**
     * Execute a confirmed redemption.
     *
     * @throws ValidationException
     * @return array{redemption_id: string, benefit_amount: float, customer_type: int, customer_type_label: string, duplicate: bool}
     */
    public function execute(
        User    $cashier,
        int     $partnershipId,
        int     $outletId,
        float   $billAmount,
        string  $claimToken,
        string  $transactionId,  // idempotency key from POS
        ?string $billId = null,
        ?int    $customerId = null,
        ?string $approvalCode = null,
    ): array {
        // ── Idempotency check ─────────────────────────────────
        $existing = DB::table('partner_redemptions')
            ->where('merchant_id', $cashier->merchant_id)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existing) {
            return [
                'redemption_id'      => $existing->uuid,
                'benefit_amount'     => (float) $existing->benefit_amount,
                'customer_type'      => $existing->customer_type,
                'customer_type_label'=> CustomerType::label($existing->customer_type),
                'duplicate'          => true,
            ];
        }

        // ── Redemption-enabled check ──────────────────────────
        // The outlet performing the redemption belongs to a specific merchant.
        // If that merchant has redemption_enabled=false on their participant row, deny.
        $outletMerchantId = DB::table('outlets')
            ->where('id', $outletId)
            ->value('merchant_id');

        if ($outletMerchantId) {
            $targetParticipant = DB::table('partnership_participants')
                ->where('partnership_id', $partnershipId)
                ->where('merchant_id', $outletMerchantId)
                ->whereNull('deleted_at')
                ->first();

            if ($targetParticipant && !$targetParticipant->redemption_enabled) {
                throw ValidationException::withMessages([
                    'claim_token' => ['Redemptions are currently disabled at this outlet for this partnership.'],
                ]);
            }
        }

        // ── Evaluate rules ────────────────────────────────────
        $ctx = new RedemptionContext(
            partnershipId: $partnershipId,
            merchantId:    $cashier->merchant_id,
            outletId:      $outletId,
            billAmount:    $billAmount,
            claimToken:    $claimToken,
            attemptedAt:   now(),
            customerId:    $customerId,
        );

        $result = $this->engine->evaluate($ctx);

        if (!$result->allowed) {
            throw ValidationException::withMessages([
                'claim_token' => [$result->reasonDisplay ?? 'Redemption not allowed.'],
            ]);
        }

        // ── Approval code enforcement ─────────────────────────
        if ($result->requiresApproval) {
            if (empty($approvalCode)) {
                throw ValidationException::withMessages([
                    'approval_code' => ['Manager approval is required for this redemption.'],
                ]);
            }

            if (!$this->approval->consume($claimToken, $partnershipId, $approvalCode)) {
                throw ValidationException::withMessages([
                    'approval_code' => ['Invalid or expired approval code.'],
                ]);
            }
        }

        // ── Resolve rule version (D-003 LOCKED) ──────────────
        // Upsert into partnership_rule_versions; get the id.
        // The JSON snapshot is also kept inline on the redemption row as a human-readable fallback.
        [$ruleVersionId, $ruleSnapshot] = $this->resolveRuleVersion($partnershipId);

        // Fetch claim before transaction — need claim_id + member_id for insert and event dispatch.
        // RulesEngineService already validated this claim exists and is valid.
        $claim = DB::table('partner_claims')
            ->where('token', $claimToken)
            ->select('id', 'member_id')
            ->first();

        // ── Write redemption + update claim ──────────────────
        $uuid      = (string) Str::uuid();
        $capResult = CapEnforcementService::RESULT_OK; // updated by reference inside transaction

        $redemptionId = DB::transaction(function () use (
            $uuid, $cashier, $partnershipId, $outletId,
            $billAmount, $claimToken, $transactionId, $billId,
            $customerId, $claim, $result, $ruleSnapshot, $ruleVersionId, $ctx, &$capResult
        ): int {
            // a) Insert redemption record
            $redemptionId = DB::table('partner_redemptions')->insertGetId([
                'uuid'            => $uuid,
                'merchant_id'     => $cashier->merchant_id,
                'partnership_id'  => $partnershipId,
                'claim_id'        => $claim?->id,
                'outlet_id'       => $outletId,
                'member_id'       => $claim?->member_id,  // persisted so CustomerClassifier finds future visits
                'customer_id'     => $customerId,
                'bill_id'         => $billId,
                'transaction_id'  => $transactionId,
                'bill_amount'     => $billAmount,
                'benefit_amount'  => $result->maxBenefitAmount,
                'customer_type'   => $result->customerType,
                'rule_snapshot'   => json_encode($ruleSnapshot),   // inline fallback (D-003)
                'rule_version_id' => $ruleVersionId,               // FK to versioned table (D-003)
                'approved_by'     => $cashier->id,
                'approval_method' => $result->requiresApproval ? 2 : 1,
                'status'          => 1, // completed
                'created_by'      => $cashier->id,
                'updated_by'      => $cashier->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // b) Mark claim as redeemed
            DB::table('partner_claims')
                ->where('token', $claimToken)
                ->update([
                    'status'      => 2, // redeemed
                    'redeemed_at' => now(),
                    'updated_at'  => now(),
                    'updated_by'  => $cashier->id,
                ]);

            // c) Atomic cap increment (SELECT FOR UPDATE inside)
            $terms = PartnershipTerms::where('partnership_id', $partnershipId)->first();

            if ($terms) {
                $capResult = $this->caps->increment(
                    $terms,
                    $cashier->merchant_id,
                    $partnershipId,
                    $outletId,
                    $result->maxBenefitAmount,
                    $ctx->attemptedAt,
                );

                if ($capResult === CapEnforcementService::RESULT_RACE_FAILED) {
                    // Race condition caught at lock time — roll back entire transaction
                    throw new \RuntimeException('Cap exhausted during lock — please retry.');
                }
            }

            return $redemptionId;
        });

        // ── Dispatch events (outside transaction) ─────────────
        RedemptionExecuted::dispatch(
            $redemptionId,
            $partnershipId,
            $cashier->merchant_id,
            $outletId,
            $result->customerType,
            $result->maxBenefitAmount,
            $claim?->member_id,
        );

        if (in_array($result->customerType, [CustomerType::NEW, CustomerType::REACTIVATED], true)) {
            FirstVisitViaPartnership::dispatch(
                $redemptionId,
                $partnershipId,
                $cashier->merchant_id,
                $outletId,
                $result->customerType,
                $claim?->member_id,
            );
        }

        // ── Cap exhaustion alert ───────────────────────────────
        // Fire when the increment just brought the counter to/past the ceiling.
        if ($capResult === CapEnforcementService::RESULT_CAP_HIT) {
            PartnershipCapExhausted::dispatch(
                $partnershipId,
                $cashier->merchant_id,
                'monthly',
                $outletId,
                (int) $ctx->attemptedAt->format('Y'),
                (int) $ctx->attemptedAt->format('n'),
            );
        }

        return [
            'redemption_id'       => $uuid,
            'benefit_amount'      => $result->maxBenefitAmount,
            'customer_type'       => $result->customerType,
            'customer_type_label' => CustomerType::label($result->customerType),
            'duplicate'           => false,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Resolve (or create) a PartnershipRuleVersion row for the current terms + rules state.
     *
     * Returns [int $ruleVersionId, array $snapshotArray].
     *
     * D-003 LOCKED 2026-04-10: versioned table + inline JSON fallback.
     * - UNIQUE KEY (partnership_id, terms_version, rules_version) guarantees deduplication.
     * - firstOrCreate is idempotent: multiple concurrent redemptions under the same version
     *   all resolve to the same row without duplication.
     * - $snapshotArray is also stored inline on partner_redemptions.rule_snapshot as
     *   a human-readable fallback until Phase 2 drops that column.
     *
     * @return array{int, array}
     */
    private function resolveRuleVersion(int $partnershipId): array
    {
        $terms = DB::table('partnership_terms')
            ->where('partnership_id', $partnershipId)
            ->first();

        $rules = DB::table('partnership_rules')
            ->where('partnership_id', $partnershipId)
            ->first();

        $termsVersion = $terms?->version ?? 1;
        $rulesVersion = $rules?->version  ?? 1;

        $version = PartnershipRuleVersion::firstOrCreate(
            [
                'partnership_id' => $partnershipId,
                'terms_version'  => $termsVersion,
                'rules_version'  => $rulesVersion,
            ],
            [
                'terms_snapshot'  => $terms ? (array) $terms : null,
                'rules_snapshot'  => $rules ? (array) $rules : null,
                'effective_from'  => now(),
                'created_at'      => now(),
            ]
        );

        $snapshotArray = [
            'snapshot_at'  => now()->toIso8601String(),
            'terms_version'=> $termsVersion,
            'rules_version'=> $rulesVersion,
            'terms'        => $terms ? (array) $terms : null,
            'rules'        => $rules ? (array) $rules : null,
        ];

        return [$version->id, $snapshotArray];
    }
}
