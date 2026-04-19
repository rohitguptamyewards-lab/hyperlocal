<?php

namespace App\Modules\EwardsRequest\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EwardsRequest\Services\EwardsRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant-facing eWards integration request endpoints.
 *
 * GET  /api/merchant/ewards-request      — current request status
 * POST /api/merchant/ewards-request      — submit new request
 *
 * Owner module: EwardsRequest
 */
class EwardsRequestController extends Controller
{
    public function __construct(
        private readonly EwardsRequestService $service,
    ) {}

    /**
     * GET /api/merchant/ewards-request
     * Returns the current request for this merchant, or null.
     */
    public function show(Request $request): JsonResponse
    {
        $req = $this->service->getForMerchant($request->user()->merchant_id);

        if (!$req) {
            return response()->json(['request' => null]);
        }

        return response()->json([
            'request' => [
                'uuid'             => $req->uuid,
                'status'           => $req->status,
                'notes'            => $req->notes,
                'rejection_reason' => $req->rejection_reason,
                'reviewed_at'      => $req->reviewed_at?->toIso8601String(),
                'created_at'       => $req->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/merchant/ewards-request
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $req = $this->service->submit(
            merchantId:  $request->user()->merchant_id,
            requestedBy: $request->user()->id,
            notes:       $data['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Your eWards integration request has been submitted. You will be notified when it is reviewed.',
            'uuid'    => $req->uuid,
            'status'  => $req->status,
        ], 201);
    }
}
