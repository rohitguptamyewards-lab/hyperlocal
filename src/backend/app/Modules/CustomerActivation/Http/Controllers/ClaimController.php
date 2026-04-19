<?php

namespace App\Modules\CustomerActivation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerActivation\Http\Requests\CreateClaimRequest;
use App\Modules\CustomerActivation\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    /**
     * Issue a claim token.
     * Called when the customer scans the QR and taps claim in WhatsApp.
     * Returns the token to be shown to the cashier.
     */
    public function store(CreateClaimRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $user   = $request->user();

        $partnership = \App\Modules\Partnership\Models\Partnership::where('uuid', $data['partnership_uuid'])->firstOrFail();

        $result = $this->claims->issue(
            partnershipId:  $partnership->id,
            merchantId:     $user->merchant_id,
            sourceOutletId: $data['source_outlet_id'],
            targetOutletId: $data['target_outlet_id'],
            issuedByUserId: $user->id,
            customerId:     null, // walk-in — no user account
            customerPhone:  $data['customer_phone'] ?? null,
        );

        return response()->json($result, 201);
    }
}
