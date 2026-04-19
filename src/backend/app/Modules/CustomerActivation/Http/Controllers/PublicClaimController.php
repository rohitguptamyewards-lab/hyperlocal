<?php

namespace App\Modules\CustomerActivation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\CustomerActivation\Services\ClaimService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) endpoints for the customer QR self-claim flow.
 *
 * Purpose: Customer scans a QR code at the source outlet and self-claims a
 *          referral token without needing an account. The QR encodes the
 *          partnership UUID and the source outlet ID.
 *
 * Rate limiting: applied in routes/api.php via throttle middleware.
 *
 * Owner module: CustomerActivation
 * Integration points: ClaimService (same token issuance path as staff claims)
 * DO NOT add authentication middleware to these routes.
 */
class PublicClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    /**
     * Return the public-facing partnership info needed to render the claim page.
     * Source outlet is identified by the ?from= query param (set in the QR URL).
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)
            ->where('status', 5) // LIVE only
            ->with('participants')
            ->first();

        if (!$partnership) {
            return response()->json(['error' => 'This partnership is not currently active.'], 404);
        }

        $sourceOutletId = (int) $request->query('from', 0);

        // Derive source merchant from the outlet
        $sourceOutlet = $sourceOutletId
            ? Outlet::find($sourceOutletId)
            : null;

        // Build target outlets — the OTHER side of the partnership
        $targetOutlets = collect();
        foreach ($partnership->participants as $participant) {
            // Skip the source merchant's side
            if ($sourceOutlet && (int) $participant->merchant_id === (int) $sourceOutlet->merchant_id) {
                continue;
            }

            if ($participant->outlet_id !== null) {
                $outlet = Outlet::find($participant->outlet_id);
                if ($outlet) {
                    $targetOutlets->push(['id' => $outlet->id, 'name' => $outlet->name, 'address' => $outlet->address]);
                }
            } else {
                // Brand-wide — all active outlets for that merchant
                $outlets = Outlet::where('merchant_id', $participant->merchant_id)
                    ->where('is_active', true)
                    ->get(['id', 'name', 'address']);
                foreach ($outlets as $o) {
                    $targetOutlets->push(['id' => $o->id, 'name' => $o->name, 'address' => $o->address]);
                }
            }
        }

        return response()->json([
            'partnership_name' => $partnership->name,
            'source_outlet'    => $sourceOutlet ? ['id' => $sourceOutlet->id, 'name' => $sourceOutlet->name] : null,
            'target_outlets'   => $targetOutlets->unique('id')->values(),
        ]);
    }

    /**
     * Self-service claim issuance — customer requests a token via the QR landing page.
     * No authentication required. Rate limited to 5 per IP per hour in routes/api.php.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'partnership_uuid'  => ['required', 'string', 'exists:partnerships,uuid'],
            'source_outlet_id'  => ['required', 'integer', 'exists:outlets,id'],
            'target_outlet_id'  => ['required', 'integer', 'exists:outlets,id'],
            'customer_phone'    => ['required', 'string', 'max:20'],
        ]);

        $partnership = Partnership::where('uuid', $data['partnership_uuid'])->firstOrFail();

        // Verify the source outlet belongs to a participant in this partnership
        $sourceOutlet = Outlet::findOrFail($data['source_outlet_id']);
        $sourceIsParticipant = $partnership->participants()
            ->where('merchant_id', $sourceOutlet->merchant_id)
            ->exists();

        if (!$sourceIsParticipant) {
            return response()->json(['error' => 'Invalid source outlet for this partnership.'], 422);
        }

        $result = $this->claims->issue(
            partnershipId:  $partnership->id,
            merchantId:     $sourceOutlet->merchant_id,
            sourceOutletId: $data['source_outlet_id'],
            targetOutletId: $data['target_outlet_id'],
            issuedByUserId: 0,   // sentinel: self-service, no staff user
            customerId:     null,
            customerPhone:  $data['customer_phone'],
        );

        return response()->json($result, 201);
    }
}
