<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Execution\Models\ShareableLink;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GAP 10 — Link-Based Token Flow
 *
 * POST /api/partnerships/{uuid}/share-link  (auth) — generate / retrieve a shareable link
 * GET  /api/shared-claim/{code}             (public) — return partnership preview for landing page
 */
class ShareableLinkController extends Controller
{
    /**
     * POST /api/partnerships/{uuid}/share-link
     *
     * Idempotent — if a share link already exists for this partnership + merchant,
     * the same code is returned. Otherwise a new 8-char uppercase code is created.
     *
     * Returns the full shareable URL in the form:
     *   /shared/{code}
     */
    public function generateLink(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $myMerchantId = $request->user()->merchant_id;

        $amParticipant = $partnership->participants()
            ->where('merchant_id', $myMerchantId)
            ->exists();

        if (!$amParticipant) {
            return response()->json(['message' => 'You are not a participant in this partnership.'], 403);
        }

        // Only Live or Paused partnerships can be shared
        if (!in_array($partnership->status, [5, 6], true)) {
            return response()->json([
                'message' => 'Share links can only be generated for live or paused partnerships.',
            ], 422);
        }

        // Idempotent: reuse existing link for this merchant+partnership
        $link = ShareableLink::firstOrCreate(
            [
                'partnership_id'         => $partnership->id,
                'created_by_merchant_id' => $myMerchantId,
            ],
            [
                'code' => $this->generateUniqueCode(),
            ]
        );

        $baseUrl     = rtrim(config('app.url'), '/');
        $shareUrl    = "{$baseUrl}/shared/{$link->code}";
        $claimUrl    = "{$baseUrl}/claim/{$uuid}?ref=SHARE_{$link->code}";

        return response()->json([
            'code'      => $link->code,
            'share_url' => $shareUrl,
            'claim_url' => $claimUrl,
            'created_at' => $link->created_at?->toIso8601String(),
        ], 200);
    }

    /**
     * GET /api/shared-claim/{code}   — PUBLIC, no auth
     *
     * Returns enough partnership info to render the landing page.
     * Also increments click_count.
     */
    public function claimViaLink(string $code): JsonResponse
    {
        $link = ShareableLink::where('code', strtoupper($code))
            ->with(['partnership.participants', 'partnership.terms'])
            ->firstOrFail();

        // Increment click counter (fire-and-forget)
        $link->increment('click_count');

        $partnership  = $link->partnership;
        $participants = $partnership->participants ?? collect();

        $proposer = $participants->firstWhere('role', 1);
        $acceptor = $participants->firstWhere('role', 2);

        $terms = $partnership->terms;

        // Build human-readable offer summary
        $offerSummary = null;
        if ($terms) {
            if ($terms->per_bill_cap_percent !== null) {
                $offerSummary = "{$terms->per_bill_cap_percent}% off your bill";
                if ($terms->per_bill_cap_amount !== null) {
                    $offerSummary .= " (up to ₹{$terms->per_bill_cap_amount})";
                }
            } elseif ($terms->per_bill_cap_amount !== null) {
                $offerSummary = "₹{$terms->per_bill_cap_amount} off your bill";
            } else {
                $offerSummary = 'Exclusive partner benefit';
            }

            if ($terms->min_bill_amount !== null) {
                $offerSummary .= " · Min bill ₹{$terms->min_bill_amount}";
            }
        }

        return response()->json([
            'code'             => $link->code,
            'partnership_uuid' => $partnership->uuid,
            'partnership_name' => $partnership->name,
            'proposer_name'    => $proposer?->merchant_name ?? 'Partner',
            'acceptor_name'    => $acceptor?->merchant_name ?? 'Partner',
            'offer_summary'    => $offerSummary,
            'terms'            => $terms ? [
                'per_bill_cap_percent' => $terms->per_bill_cap_percent,
                'per_bill_cap_amount'  => $terms->per_bill_cap_amount,
                'min_bill_amount'      => $terms->min_bill_amount,
                'monthly_cap_amount'   => $terms->monthly_cap_amount,
            ] : null,
            'claim_url'        => '/claim/' . $partnership->uuid . '?ref=SHARE_' . $link->code,
        ]);
    }

    // -------------------------------------------------------------------------

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ShareableLink::where('code', $code)->exists());

        return $code;
    }
}
