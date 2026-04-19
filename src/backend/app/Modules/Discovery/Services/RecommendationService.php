<?php

namespace App\Modules\Discovery\Services;

use App\Models\Merchant;
use App\Models\Outlet;
use App\Modules\Discovery\Models\Recommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RecommendationService — orchestrates partner recommendation computation and retrieval.
 *
 * Owner module: Discovery
 * Reads:   merchants, outlets, partnerships, partnership_participants
 * Writes:  partner_recommendations (upsert only)
 *
 * Called by:
 *   - ComputeRecommendations artisan command (nightly batch)
 *   - DiscoveryController::suggestions() (read-only path — does NOT recompute)
 *
 * Minimum fit score to store: FIT_SCORE_FLOOR (0.20)
 * Maximum suggestions shown per merchant: MAX_SUGGESTIONS (5)
 */
class RecommendationService
{
    private const FIT_SCORE_FLOOR  = 0.20;
    private const MAX_SUGGESTIONS  = 5;
    private const EXPIRY_DAYS      = 30;

    public function __construct(private readonly FitScoringService $scoring) {}

    // ── Compute (batch job) ───────────────────────────────────

    /**
     * Compute and upsert recommendations for all active merchants,
     * or a single merchant if $merchantId is provided.
     *
     * @param  int|null $merchantId  Optional: restrict to one merchant
     * @return array{processed: int, stored: int}
     */
    public function compute(?int $merchantId = null): array
    {
        $merchants = Merchant::where('is_active', true)
            ->when($merchantId, fn ($q) => $q->where('id', $merchantId))
            ->with('outlets')
            ->get();

        $processed = 0;
        $stored    = 0;

        foreach ($merchants as $merchant) {
            $count   = $this->computeForMerchant($merchant);
            $stored += $count;
            $processed++;
        }

        // Purge stale expired rows
        Recommendation::where('expires_at', '<', now())->delete();

        return ['processed' => $processed, 'stored' => $stored];
    }

    /**
     * Compute and upsert recommendations for one merchant.
     * Returns the number of recommendation rows written.
     */
    public function computeForMerchant(Merchant $merchant): int
    {
        $sourceOutlets = $merchant->outlets->where('is_active', true);

        // Merchants already partnered (any status except EXPIRED=7) — exclude them
        $existingPartnerIds = $this->existingPartnerIds($merchant->id);

        // Candidate merchants: active, not self, not already partnered
        $candidates = Merchant::where('is_active', true)
            ->where('id', '!=', $merchant->id)
            ->whereNotIn('id', $existingPartnerIds)
            ->with('outlets')
            ->get();

        // Cluster size: active partnerships (status=5/LIVE) sharing same city as this merchant
        $clusterSize = $this->clusterSize($merchant->city);

        $written = 0;

        foreach ($candidates as $candidate) {
            $targetOutlets = $candidate->outlets->where('is_active', true);

            $result = $this->scoring->score(
                $merchant,
                $candidate,
                $sourceOutlets,
                $targetOutlets,
                $clusterSize,
            );

            if ($result['score'] < self::FIT_SCORE_FLOOR) {
                continue;
            }

            Recommendation::updateOrCreate(
                [
                    'merchant_id'             => $merchant->id,
                    'recommended_merchant_id' => $candidate->id,
                ],
                [
                    'fit_score'       => $result['score'],
                    'rationale'       => $result['rationale'],
                    'confidence_tier' => $result['tier'],
                    'status'          => Recommendation::STATUS_ACTIVE,
                    'computed_at'     => now(),
                    'expires_at'      => now()->addDays(self::EXPIRY_DAYS),
                ]
            );

            $written++;
        }

        return $written;
    }

    // ── Read (API path) ───────────────────────────────────────

    /**
     * Return top N active, non-expired suggestions for a merchant,
     * ordered by fit_score descending.
     *
     * @return Collection<int, Recommendation>
     */
    public function suggestionsFor(int $merchantId): Collection
    {
        return Recommendation::activeFor($merchantId)
            ->with('recommendedMerchant.outlets')
            ->orderByDesc('fit_score')
            ->limit(self::MAX_SUGGESTIONS)
            ->get();
    }

    /**
     * Mark a recommendation as dismissed.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function dismiss(int $recommendationId, int $merchantId): Recommendation
    {
        $rec = Recommendation::where('id', $recommendationId)
            ->where('merchant_id', $merchantId)
            ->firstOrFail();

        $rec->update([
            'status'       => Recommendation::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);

        return $rec;
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * IDs of merchants already connected to $merchantId in any active/in-progress partnership.
     * Statuses 2 (REQUESTED) through 6 (PAUSED) are all "connected" — exclude them.
     */
    private function existingPartnerIds(int $merchantId): array
    {
        // Find all partnership IDs this merchant participates in (statuses 2-6)
        $partnershipIds = DB::table('partnership_participants')
            ->where('merchant_id', $merchantId)
            ->pluck('partnership_id');

        if ($partnershipIds->isEmpty()) {
            return [];
        }

        // Statuses to exclude: 2=REQUESTED, 3=NEGOTIATING, 4=AGREED, 5=LIVE, 6=PAUSED
        $activeStatuses = [2, 3, 4, 5, 6];

        $relevantPartnershipIds = DB::table('partnerships')
            ->whereIn('id', $partnershipIds)
            ->whereIn('status', $activeStatuses)
            ->pluck('id');

        if ($relevantPartnershipIds->isEmpty()) {
            return [];
        }

        // Get the other participant merchant IDs
        return DB::table('partnership_participants')
            ->whereIn('partnership_id', $relevantPartnershipIds)
            ->where('merchant_id', '!=', $merchantId)
            ->pluck('merchant_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Count LIVE (status=5) partnerships whose participants share the same city.
     */
    private function clusterSize(?string $city): int
    {
        if ($city === null) {
            return 0;
        }

        return (int) DB::table('partnerships as p')
            ->join('partnership_participants as pp', 'pp.partnership_id', '=', 'p.id')
            ->join('merchants as m', 'm.id', '=', 'pp.merchant_id')
            ->where('p.status', 5)
            ->where('m.city', $city)
            ->distinct('p.id')
            ->count('p.id');
    }
}
