<?php

namespace App\Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\Network\Models\PartnerRating;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GAP 7 — Partner Rating / Trust System
 *
 * POST /api/partnerships/{uuid}/rate          — submit or update a rating
 * GET  /api/partnerships/{uuid}/ratings       — list ratings for a partnership
 * GET  /api/merchants/{id}/ratings            — all ratings a merchant has received
 */
class PartnerRatingController extends Controller
{
    /**
     * POST /api/partnerships/{uuid}/rate
     *
     * The authenticated merchant rates their partner in this partnership.
     * Only allowed when partnership status is Live (5) or Paused (6).
     * Recalculates and persists the rated merchant's trust_score on every save.
     */
    public function rate(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        // Only Live (5) or Paused (6)
        if (!in_array($partnership->status, [5, 6], true)) {
            return response()->json([
                'message' => 'You can only rate a partner once the partnership is live or paused.',
            ], 422);
        }

        $myMerchantId = $request->user()->merchant_id;

        // Confirm the caller is a participant
        $participants  = $partnership->participants()->get();
        $amParticipant = $participants->contains('merchant_id', $myMerchantId);
        if (!$amParticipant) {
            return response()->json(['message' => 'You are not a participant in this partnership.'], 403);
        }

        // The partner being rated is the other side
        $partnerMerchantId = $participants
            ->firstWhere('merchant_id', '!=', $myMerchantId)
            ?->merchant_id;

        if (!$partnerMerchantId) {
            return response()->json(['message' => 'Could not resolve partner merchant.'], 422);
        }

        // Upsert the rating (one per rater per partnership)
        $rating = PartnerRating::updateOrCreate(
            [
                'partnership_id'       => $partnership->id,
                'rated_by_merchant_id' => $myMerchantId,
            ],
            [
                'rated_merchant_id' => $partnerMerchantId,
                'rating'            => $data['rating'],
                'review_text'       => $data['review_text'] ?? null,
            ]
        );

        // Recalculate and persist trust_score for the rated merchant
        $avg = PartnerRating::where('rated_merchant_id', $partnerMerchantId)
            ->avg('rating');

        Merchant::where('id', $partnerMerchantId)
            ->update(['trust_score' => round((float) $avg, 2)]);

        return response()->json([
            'message' => 'Rating saved.',
            'rating'  => $this->formatRating($rating),
        ], 200);
    }

    /**
     * GET /api/partnerships/{uuid}/ratings
     *
     * Returns both ratings for this partnership (one per side, if submitted).
     */
    public function getRatings(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $myMerchantId = $request->user()->merchant_id;
        $amParticipant = $partnership->participants()
            ->where('merchant_id', $myMerchantId)
            ->exists();

        if (!$amParticipant) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $ratings = PartnerRating::where('partnership_id', $partnership->id)
            ->with(['ratedBy', 'ratedMerchant'])
            ->get()
            ->map(fn ($r) => $this->formatRating($r));

        // Also surface whether the calling merchant has already rated
        $myRating = PartnerRating::where('partnership_id', $partnership->id)
            ->where('rated_by_merchant_id', $myMerchantId)
            ->first();

        return response()->json([
            'ratings'    => $ratings,
            'my_rating'  => $myRating ? $this->formatRating($myRating) : null,
        ]);
    }

    /**
     * GET /api/merchants/{id}/ratings
     *
     * All ratings received by a specific merchant.
     * Any authenticated user can view.
     */
    public function getMerchantRatings(Request $request, int $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);

        $ratings = PartnerRating::where('rated_merchant_id', $id)
            ->with(['ratedBy', 'partnership'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->formatRating($r));

        return response()->json([
            'merchant_id'  => $merchant->id,
            'merchant_name' => $merchant->name,
            'trust_score'  => $merchant->trust_score,
            'ratings'      => $ratings,
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatRating(PartnerRating $r): array
    {
        return [
            'id'                    => $r->id,
            'partnership_id'        => $r->partnership_id,
            'rated_by_merchant_id'  => $r->rated_by_merchant_id,
            'rated_by_merchant_name' => $r->ratedBy?->name,
            'rated_merchant_id'     => $r->rated_merchant_id,
            'rated_merchant_name'   => $r->ratedMerchant?->name,
            'rating'                => $r->rating,
            'review_text'           => $r->review_text,
            'created_at'            => $r->created_at?->toIso8601String(),
            'updated_at'            => $r->updated_at?->toIso8601String(),
        ];
    }
}
