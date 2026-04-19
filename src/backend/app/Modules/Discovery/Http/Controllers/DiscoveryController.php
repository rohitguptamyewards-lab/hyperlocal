<?php

namespace App\Modules\Discovery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\Discovery\Http\Resources\RecommendationResource;
use App\Modules\Discovery\Models\Recommendation;
use App\Modules\Discovery\Services\FitScoringService;
use App\Modules\Discovery\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DiscoveryController — serves pre-computed partner suggestions and active search.
 *
 * Owner module: Discovery
 * Reads:  partner_recommendations (via RecommendationService), merchants, partner_recommendations
 * Writes: status / dismissed_at on dismiss
 *
 * Endpoints:
 *   GET  /api/discovery/suggestions              → top 5 active suggestions for current merchant
 *   POST /api/discovery/suggestions/{id}/dismiss → mark as dismissed
 *   GET  /api/discovery/search                   → search merchants by city / category
 */
class DiscoveryController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
        private readonly FitScoringService $fitScoring,
    ) {}

    /**
     * GET /api/discovery/suggestions
     * Returns top 5 non-expired active suggestions, ordered by fit_score desc.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $suggestions = $this->recommendations->suggestionsFor(
            $request->user()->merchant_id,
        );

        return response()->json(
            RecommendationResource::collection($suggestions),
        );
    }

    /**
     * POST /api/discovery/suggestions/{id}/dismiss
     * Merchant says "not interested" — marks recommendation as dismissed.
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $rec = $this->recommendations->dismiss($id, $request->user()->merchant_id);

        return response()->json(['dismissed' => true, 'id' => $rec->id]);
    }

    /**
     * GET /api/discovery/search
     *
     * Active search for potential partners by city and optional category.
     * Includes all active merchants (even already-partnered ones) so the merchant can
     * see the full landscape.  Already-partnered results are marked with already_partnered=true
     * and carry the partnership UUID so the frontend can link to it.
     *
     * Query params:
     *   city     (required) string
     *   category (optional) string
     *
     * @return JsonResponse  array of { id, name, category, city, outlet_count, fit_score,
     *                                  rationale, already_partnered, partnership_uuid, partnership_status }
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city'     => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $myMerchantId = $request->user()->merchant_id;

        // Build a map of  partner_merchant_id => { partnership_uuid, partnership_status }
        // for any active/in-progress partnership (statuses 1–6).
        $myPartnershipIds = DB::table('partnership_participants')
            ->where('merchant_id', $myMerchantId)
            ->pluck('partnership_id');

        $alreadyPartneredMap = DB::table('partnership_participants as pp')
            ->join('partnerships as p', 'p.id', '=', 'pp.partnership_id')
            ->whereIn('pp.partnership_id', $myPartnershipIds)
            ->where('pp.merchant_id', '!=', $myMerchantId)
            ->whereIn('p.status', [1, 2, 3, 4, 5, 6])
            ->select('pp.merchant_id', 'p.uuid as partnership_uuid', 'p.status as partnership_status')
            ->get()
            ->keyBy('merchant_id'); // keeps first row if multiple (shouldn't happen)

        $query = Merchant::where('id', '!=', $myMerchantId)
            ->where('is_active', true)
            ->where('open_to_partnerships', true)
            ->where('city', $validated['city']);

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $merchants = $query
            ->withCount(['outlets' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'city', 'open_to_partnerships', 'trust_score']);

        // Attach pre-computed fit scores where available
        $fitScores = Recommendation::where('merchant_id', $myMerchantId)
            ->whereIn('recommended_merchant_id', $merchants->pluck('id'))
            ->get(['recommended_merchant_id', 'fit_score', 'rationale', 'confidence_tier'])
            ->keyBy('recommended_merchant_id');

        $results = $merchants->map(function (Merchant $m) use ($fitScores, $alreadyPartneredMap) {
            $rec          = $fitScores->get($m->id);
            $partneredRow = $alreadyPartneredMap->get($m->id);
            return [
                'id'                  => $m->id,
                'name'                => $m->name,
                'category'            => $m->category,
                'city'                => $m->city,
                'outlet_count'        => $m->outlets_count,
                'fit_score'           => $rec ? round($rec->fit_score * 100) : null,
                'confidence_tier'     => $rec ? $rec->confidence_tier : null,
                'rationale'           => $rec ? $rec->rationale : null,
                'trust_score'         => $m->trust_score !== null ? (float) $m->trust_score : null,
                'already_partnered'   => $partneredRow !== null,
                'partnership_uuid'    => $partneredRow?->partnership_uuid,
                'partnership_status'  => $partneredRow?->partnership_status,
            ];
        })->sortByDesc('fit_score')->values();

        return response()->json($results);
    }
}
