<?php

namespace App\Modules\Discovery\Services;

use App\Models\Merchant;
use App\Models\Outlet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FitScoringService — computes a fit score (0.0 to 1.0) between two merchants.
 *
 * Owner module: Discovery
 * Reads:        merchants, outlets, partnerships, partnership_participants
 * Writes:       nothing
 *
 * Score formula (V1):
 *   fit_score = (0.50 × category_score) + (0.35 × geo_score) + (0.15 × density_score)
 *
 * DO NOT MODIFY the weights without a product decision — they are intentionally conservative.
 */
class FitScoringService
{
    // ── Scoring weights ────────────────────────────────────────

    private const WEIGHT_CATEGORY = 0.50;
    private const WEIGHT_GEO      = 0.35;
    private const WEIGHT_DENSITY  = 0.15;

    // ── Confidence thresholds ──────────────────────────────────

    private const TIER_HIGH_MIN   = 0.65;
    private const TIER_MEDIUM_MIN = 0.35;

    // ── Category complementarity matrix (V1 static) ───────────
    // Key = category slug. Value = list of complementary categories.
    // Scoring:
    //   Both mention each other → 1.0 (bidirectional)
    //   Only one mentions the other → 0.7 (unidirectional)
    //   Same category → 0.0 (competitor)
    //   Neither listed → 0.3 (neutral / unknown)

    private const CATEGORY_MAP = [
        'cafe'        => ['gym', 'yoga', 'bookstore', 'salon', 'coworking', 'pharmacy', 'florist'],
        'gym'         => ['cafe', 'restaurant', 'sports_apparel', 'pharmacy', 'salon', 'yoga', 'smoothie'],
        'restaurant'  => ['gym', 'cinema', 'retail', 'salon', 'bookstore', 'coworking'],
        'salon'       => ['spa', 'gym', 'cafe', 'boutique', 'yoga', 'restaurant'],
        'bookstore'   => ['cafe', 'stationery', 'coworking', 'restaurant'],
        'spa'         => ['salon', 'gym', 'yoga', 'cafe'],
        'yoga'        => ['cafe', 'gym', 'spa', 'salon', 'pharmacy'],
        'pharmacy'    => ['gym', 'cafe', 'clinic', 'yoga'],
        'cinema'      => ['restaurant', 'cafe', 'retail'],
        'retail'      => ['cafe', 'restaurant', 'cinema'],
        'coworking'   => ['cafe', 'restaurant', 'bookstore'],
        'boutique'    => ['salon', 'cafe', 'spa'],
        'florist'     => ['cafe', 'restaurant'],
        'smoothie'    => ['gym', 'yoga', 'cafe'],
        'stationery'  => ['bookstore', 'coworking'],
        'sports_apparel' => ['gym', 'yoga'],
        'clinic'      => ['pharmacy', 'gym'],
    ];

    // ── Public API ────────────────────────────────────────────

    /**
     * Compute fit score between two merchants.
     *
     * @param  Merchant                      $source  The merchant receiving the suggestion
     * @param  Merchant                      $target  The merchant being suggested
     * @param  Collection<int, Outlet>       $sourceOutlets
     * @param  Collection<int, Outlet>       $targetOutlets
     * @param  int                           $clusterSize  Active partnerships in shared city/pincode
     * @return array{score: float, tier: int, rationale: string}
     */
    public function score(
        Merchant $source,
        Merchant $target,
        Collection $sourceOutlets,
        Collection $targetOutlets,
        int $clusterSize,
    ): array {
        $categoryScore = $this->categoryScore($source->category, $target->category);
        $geoScore      = $this->geoScore($sourceOutlets, $targetOutlets);
        $densityScore  = $this->densityScore($clusterSize);

        $fitScore = round(
            (self::WEIGHT_CATEGORY * $categoryScore)
            + (self::WEIGHT_GEO      * $geoScore)
            + (self::WEIGHT_DENSITY  * $densityScore),
            4
        );

        return [
            'score'     => $fitScore,
            'tier'      => $this->confidenceTier($fitScore),
            'rationale' => $this->buildRationale(
                $source->category,
                $target->category,
                $categoryScore,
                $geoScore,
                $clusterSize,
            ),
        ];
    }

    // ── Category scoring ──────────────────────────────────────

    private function categoryScore(?string $a, ?string $b): float
    {
        if ($a === null || $b === null) {
            return 0.3; // unknown category → neutral
        }

        $aLower = strtolower($a);
        $bLower = strtolower($b);

        if ($aLower === $bLower) {
            return 0.0; // competitor
        }

        $aMap = self::CATEGORY_MAP[$aLower] ?? [];
        $bMap = self::CATEGORY_MAP[$bLower] ?? [];

        $aKnowsB = in_array($bLower, $aMap, true);
        $bKnowsA = in_array($aLower, $bMap, true);

        if ($aKnowsB && $bKnowsA) {
            return 1.0; // bidirectional complement
        }

        if ($aKnowsB || $bKnowsA) {
            return 0.7; // unidirectional
        }

        return 0.3; // neutral
    }

    // ── Geo scoring ───────────────────────────────────────────

    /**
     * Scores based on the minimum outlet-to-outlet distance (km) across all outlet pairs.
     * Uses Haversine formula — no MySQL spatial extension required.
     */
    private function geoScore(Collection $sourceOutlets, Collection $targetOutlets): float
    {
        $minKm = PHP_FLOAT_MAX;

        foreach ($sourceOutlets as $so) {
            foreach ($targetOutlets as $to) {
                if ($so->latitude === null || $to->latitude === null) {
                    continue;
                }
                $km    = $this->haversineKm(
                    (float) $so->latitude, (float) $so->longitude,
                    (float) $to->latitude, (float) $to->longitude,
                );
                $minKm = min($minKm, $km);
            }
        }

        if ($minKm === PHP_FLOAT_MAX) {
            return 0.5; // no coordinates — neutral assumption
        }

        return match (true) {
            $minKm < 1.0  => 1.0,
            $minKm < 3.0  => 0.75,
            $minKm < 10.0 => 0.4,
            default       => 0.1,
        };
    }

    /**
     * Haversine distance formula.
     *
     * @param  float $lat1  Source latitude (degrees)
     * @param  float $lon1  Source longitude (degrees)
     * @param  float $lat2  Target latitude (degrees)
     * @param  float $lon2  Target longitude (degrees)
     * @return float Distance in kilometres
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    // ── Density scoring ───────────────────────────────────────

    private function densityScore(int $clusterSize): float
    {
        return match (true) {
            $clusterSize === 0  => 0.3,
            $clusterSize <= 3   => 0.6,
            $clusterSize <= 10  => 0.85,
            default             => 1.0,
        };
    }

    // ── Confidence tier ───────────────────────────────────────

    public function confidenceTier(float $fitScore): int
    {
        if ($fitScore >= self::TIER_HIGH_MIN)   return 1;
        if ($fitScore >= self::TIER_MEDIUM_MIN) return 2;
        return 3;
    }

    // ── Rationale builder ─────────────────────────────────────

    private function buildRationale(
        ?string $catA,
        ?string $catB,
        float $categoryScore,
        float $geoScore,
        int $clusterSize,
    ): string {
        $parts = [];

        // Category part
        $a = ucfirst($catA ?? 'Unknown');
        $b = ucfirst($catB ?? 'Unknown');
        $complementLabel = match (true) {
            $categoryScore >= 1.0 => 'high cross-visit potential',
            $categoryScore >= 0.7 => 'complementary categories',
            $categoryScore >= 0.3 => 'different categories',
            default               => 'same category (competing)',
        };
        $parts[] = "{$a} + {$b}: {$complementLabel}";

        // Geo part
        $geoLabel = match (true) {
            $geoScore >= 1.0  => 'under 1 km apart',
            $geoScore >= 0.75 => '1–3 km apart',
            $geoScore >= 0.4  => '3–10 km apart',
            $geoScore >= 0.1  => 'over 10 km apart',
            default           => 'location unknown',
        };
        $parts[] = $geoLabel;

        // Cluster part
        if ($clusterSize > 0) {
            $parts[] = "{$clusterSize} active partnership" . ($clusterSize === 1 ? '' : 's') . ' nearby';
        } else {
            $parts[] = 'pioneer zone';
        }

        return implode(' · ', $parts);
    }
}
