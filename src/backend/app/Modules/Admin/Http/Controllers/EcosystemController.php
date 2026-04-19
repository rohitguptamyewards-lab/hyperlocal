<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Events\MerchantEcosystemExit;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EcosystemController — local admin toggle for E-001 ecosystem exit testing.
 *
 * Purpose: Allows local dev to simulate a merchant leaving the eWards ecosystem
 *          without needing the real eWards webhook.
 *
 * Owner module: Admin
 * Writes: merchants.ecosystem_active
 * Fires: MerchantEcosystemExit → AutoCloseOnEcosystemExit
 *
 * eWards migration: replace these endpoints with a webhook handler that
 *   verifies the eWards HMAC signature, then fires MerchantEcosystemExit.
 *
 * Routes:
 *   POST /api/admin/merchants/{id}/ecosystem/deactivate
 *   POST /api/admin/merchants/{id}/ecosystem/reactivate
 *
 * Auth: admin role only (role=1).
 * E-001 LOCKED 2026-04-10
 */
class EcosystemController extends Controller
{
    /**
     * Simulate a merchant leaving the ecosystem.
     * Sets ecosystem_active=false and fires MerchantEcosystemExit.
     */
    public function deactivate(Request $request, int $merchantId): JsonResponse
    {
        $this->requireAdmin($request);

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $merchant = Merchant::findOrFail($merchantId);

        if (!$merchant->ecosystem_active) {
            return response()->json(['message' => 'Merchant is already inactive.'], 409);
        }

        $merchant->update(['ecosystem_active' => false]);

        MerchantEcosystemExit::dispatch($merchantId, $request->reason);

        return response()->json([
            'merchant_id'      => $merchantId,
            'merchant_name'    => $merchant->name,
            'ecosystem_active' => false,
            'message'          => 'Merchant marked as ecosystem-inactive. All LIVE/PAUSED partnerships will be auto-closed.',
        ]);
    }

    /**
     * Simulate a merchant returning to the ecosystem (local dev only).
     * Sets ecosystem_active=true. Does NOT auto-reopen partnerships — that
     * requires manual re-activation per partnership.
     */
    public function reactivate(Request $request, int $merchantId): JsonResponse
    {
        $this->requireAdmin($request);

        $merchant = Merchant::findOrFail($merchantId);
        $merchant->update(['ecosystem_active' => true]);

        return response()->json([
            'merchant_id'      => $merchantId,
            'merchant_name'    => $merchant->name,
            'ecosystem_active' => true,
            'message'          => 'Merchant marked as ecosystem-active. Partnerships remain closed — re-activate them individually.',
        ]);
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 1, 403, 'Admin only.');
    }
}
