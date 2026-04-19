<?php

namespace App\Modules\MerchantSettings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\MerchantSettings\Models\MerchantPointValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant-level settings: point valuation history + update.
 *
 * Purpose: Allow merchants to declare and version their loyalty point value.
 * Owner module: MerchantSettings
 * Tables: merchant_point_valuations (read + write)
 * Integration: Partnership terms use rupees_per_point_at_agreement (locked at agreement time)
 */
class MerchantSettingsController extends Controller
{
    /**
     * GET current point valuation + full history for this merchant.
     */
    public function getPointValuation(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $history = MerchantPointValuation::forMerchant($merchantId)
            ->with('confirmedBy:id,name')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn ($v) => [
                'id'               => $v->id,
                'rupees_per_point' => (float) $v->rupees_per_point,
                'effective_from'   => $v->effective_from->toIso8601String(),
                'confirmed_by'     => $v->confirmedBy?->name ?? 'Unknown',
                'note'             => $v->note,
                'created_at'       => $v->created_at->toIso8601String(),
            ]);

        $current = $history->first(); // ordered desc, first = latest active

        return response()->json([
            'current' => $current,
            'history' => $history,
        ]);
    }

    /**
     * POST a new point valuation.
     * Always inserts — never updates existing rows (immutable history).
     *
     * Requires admin or manager role.
     * Requires a note explaining the reason for change.
     */
    public function setPointValuation(Request $request): JsonResponse
    {
        abort_if(
            !in_array($request->user()->role, [1, 2], true),
            403,
            'Only admins and managers can set the point valuation.'
        );

        $data = $request->validate([
            'rupees_per_point' => ['required', 'numeric', 'min:0.0001', 'max:100000'],
            'note'             => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $valuation = MerchantPointValuation::create([
            'merchant_id'      => $request->user()->merchant_id,
            'rupees_per_point' => $data['rupees_per_point'],
            'effective_from'   => now(),
            'confirmed_by'     => $request->user()->id,
            'note'             => $data['note'],
        ]);

        return response()->json([
            'id'               => $valuation->id,
            'rupees_per_point' => (float) $valuation->rupees_per_point,
            'effective_from'   => $valuation->effective_from->toIso8601String(),
            'note'             => $valuation->note,
        ], 201);
    }

    /**
     * GET /api/merchant/settings/discoverability
     * Returns the current open_to_partnerships flag for this merchant.
     *
     * @return JsonResponse  { open_to_partnerships: bool }
     */
    public function getDiscoverability(Request $request): JsonResponse
    {
        $merchant = Merchant::findOrFail($request->user()->merchant_id);

        return response()->json([
            'open_to_partnerships' => (bool) $merchant->open_to_partnerships,
        ]);
    }

    /**
     * POST /api/merchant/settings/discoverability
     * Toggle whether this merchant appears in Find Partners search results.
     * Requires admin or manager role.
     *
     * @param  Request  $request  { open_to_partnerships: bool }
     * @return JsonResponse       { open_to_partnerships: bool }
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function setDiscoverability(Request $request): JsonResponse
    {
        abort_if(
            !in_array($request->user()->role, [1, 2], true),
            403,
            'Only admins and managers can change discoverability.'
        );

        $data = $request->validate([
            'open_to_partnerships' => ['required', 'boolean'],
        ]);

        $merchant = Merchant::findOrFail($request->user()->merchant_id);
        $merchant->update(['open_to_partnerships' => $data['open_to_partnerships']]);

        return response()->json([
            'open_to_partnerships' => (bool) $merchant->open_to_partnerships,
        ]);
    }

    /**
     * GET /merchant/settings/bill-offers
     */
    public function getBillOffers(Request $request): JsonResponse
    {
        $merchant = Merchant::findOrFail($request->user()->merchant_id);

        return response()->json([
            'bill_offers_enabled'      => (bool) $merchant->bill_offers_enabled,
            'bill_offers_display_mode' => $merchant->bill_offers_display_mode ?? 'simple',
        ]);
    }

    /**
     * POST /merchant/settings/bill-offers
     */
    public function setBillOffers(Request $request): JsonResponse
    {
        abort_if(
            !in_array($request->user()->role, [1, 2], true),
            403,
            'Only admins and managers can change bill offer settings.'
        );

        $data = $request->validate([
            'bill_offers_enabled'      => ['sometimes', 'boolean'],
            'bill_offers_display_mode' => ['sometimes', 'string', 'in:simple,scratch,carousel'],
        ]);

        $merchant = Merchant::findOrFail($request->user()->merchant_id);
        $merchant->update($data);

        return response()->json([
            'bill_offers_enabled'      => (bool) $merchant->bill_offers_enabled,
            'bill_offers_display_mode' => $merchant->bill_offers_display_mode,
        ]);
    }
}
