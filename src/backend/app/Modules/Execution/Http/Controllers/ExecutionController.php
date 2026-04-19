<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Execution\Http\Requests\ApprovalRequest;
use App\Modules\Execution\Http\Requests\RedemptionRequest;
use App\Modules\Execution\Services\ApprovalService;
use App\Modules\Execution\Services\RedemptionService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    public function __construct(
        private readonly RedemptionService $service,
        private readonly ApprovalService   $approval,
    ) {}

    /**
     * Pre-check: evaluate eligibility before the bill is finalised.
     * Read-only — no writes, no cap increment.
     * Cashier calls this as soon as the customer shows the token.
     * partnership_id and outlet_id are auto-resolved from the claim when not provided.
     */
    public function lookup(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'partnership_id' => ['nullable', 'string', 'exists:partnerships,uuid'],
            'outlet_id'      => ['nullable', 'integer'],
            'bill_amount'    => ['required', 'numeric', 'min:0.01'],
        ]);

        // Auto-resolve partnership and outlet from the claim when not provided
        $claim = \DB::table('partner_claims')->where('token', $token)->first();

        if (!$claim) {
            return response()->json([
                'allowed'        => false,
                'reason_code'    => 'CLAIM_NOT_FOUND',
                'reason_display' => 'Token not found.',
                'fallback_help'  => 'Please check the token and try again.',
            ], 422);
        }

        if ($request->partnership_id) {
            $partnership = Partnership::where('uuid', $request->partnership_id)->firstOrFail();
        } else {
            $partnership = Partnership::findOrFail($claim->partnership_id);
        }

        $outletId = $request->outlet_id
            ? (int) $request->outlet_id
            : (int) $claim->target_outlet_id;

        $result = $this->service->evaluate(
            partnershipId: $partnership->id,
            merchantId:    $request->user()->merchant_id,
            outletId:      $outletId,
            billAmount:    (float) $request->bill_amount,
            claimToken:    $token,
            customerId:    null, // resolved from claim->member_id inside RulesEngineService
        );

        if (!$result->allowed) {
            return response()->json([
                'allowed'         => false,
                'reason_code'     => $result->reasonCode,
                'reason_display'  => $result->reasonDisplay,
                'fallback_help'   => $result->fallbackHelp,
            ], 422);
        }

        return response()->json([
            'allowed'              => true,
            'benefit_amount'       => $result->maxBenefitAmount,
            'customer_type'        => $result->customerType,
            'customer_type_label'  => \App\Modules\RulesEngine\Constants\CustomerType::label($result->customerType),
            'requires_approval'    => $result->requiresApproval,
            'partnership_uuid'     => $partnership->uuid,
            'partnership_name'     => $partnership->name,
            'outlet_id'            => $outletId,
        ]);
    }

    /**
     * Generate a manager approval code for a pending high-value redemption.
     * The code is returned in the response so the cashier UI can display it.
     * In production this would be delivered to the manager's device via WhatsApp.
     * partnership_id is auto-resolved from the claim when not provided.
     */
    public function requestApproval(ApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['partnership_id'])) {
            $partnership = Partnership::where('uuid', $data['partnership_id'])->firstOrFail();
        } else {
            $claim = \DB::table('partner_claims')->where('token', $data['claim_token'])->firstOrFail();
            $partnership = Partnership::findOrFail($claim->partnership_id);
        }

        $code = $this->approval->generate($data['claim_token'], $partnership->id);

        return response()->json([
            'code'       => $code,
            'expires_in' => ApprovalService::CODE_TTL_SECONDS,
            'note'       => 'In production, this code is sent to the manager via WhatsApp.',
        ]);
    }

    /**
     * Execute redemption — writes to DB, increments caps, fires events.
     * Idempotent: duplicate transaction_id returns the original result.
     * partnership_id and outlet_id are auto-resolved from the claim when not provided.
     */
    public function redeem(RedemptionRequest $request): JsonResponse
    {
        $data    = $request->validated();
        $cashier = $request->user();

        // Auto-resolve partnership and outlet from the claim when not provided
        $claim = \DB::table('partner_claims')->where('token', $data['claim_token'])->first();

        if ($claim === null) {
            return response()->json([
                'message' => 'Invalid or unknown claim token.',
                'errors'  => ['claim_token' => ['The token does not exist.']],
            ], 422);
        }

        if (!empty($data['partnership_id'])) {
            $partnership = Partnership::where('uuid', $data['partnership_id'])->firstOrFail();
        } else {
            $partnership = Partnership::findOrFail($claim->partnership_id);
        }

        $outletId = !empty($data['outlet_id'])
            ? $data['outlet_id']
            : (int) $claim->target_outlet_id;

        $result = $this->service->execute(
            cashier:       $cashier,
            partnershipId: $partnership->id,
            outletId:      $outletId,
            billAmount:    (float) $data['bill_amount'],
            claimToken:    $data['claim_token'],
            transactionId: $data['transaction_id'],
            billId:        $data['bill_id'] ?? null,
            customerId:    $data['customer_id'] ?? null, // never fall back to cashier ID
            approvalCode:  $data['approval_code'] ?? null,
        );

        return response()->json($result, $result['duplicate'] ? 200 : 201);
    }
}
