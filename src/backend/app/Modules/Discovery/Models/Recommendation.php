<?php

namespace App\Modules\Discovery\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recommendation — pre-computed partner suggestion for a merchant.
 *
 * Owner module: Discovery
 * Table:        partner_recommendations
 * Writes:       Discovery module only (FitScoringService / ComputeRecommendations command)
 * Reads:        DiscoveryController
 *
 * Status constants:
 *   STATUS_ACTIVE    = 1  — shown to merchant
 *   STATUS_DISMISSED = 2  — merchant said "not interested"
 *   STATUS_CONVERTED = 3  — merchant created a partnership from this suggestion
 *
 * Confidence tier:
 *   TIER_HIGH   = 1  (fit_score >= 0.65)
 *   TIER_MEDIUM = 2  (fit_score 0.35–0.64)
 *   TIER_LOW    = 3  (fit_score < 0.35)
 */
class Recommendation extends Model
{
    protected $table = 'partner_recommendations';

    public const STATUS_ACTIVE    = 1;
    public const STATUS_DISMISSED = 2;
    public const STATUS_CONVERTED = 3;

    public const TIER_HIGH   = 1;
    public const TIER_MEDIUM = 2;
    public const TIER_LOW    = 3;

    protected $fillable = [
        'merchant_id',
        'recommended_merchant_id',
        'fit_score',
        'rationale',
        'cluster_id',
        'confidence_tier',
        'status',
        'dismissed_at',
        'converted_at',
        'computed_at',
        'expires_at',
    ];

    protected $casts = [
        'fit_score'    => 'float',
        'dismissed_at' => 'datetime',
        'converted_at' => 'datetime',
        'computed_at'  => 'datetime',
        'expires_at'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function recommendedMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'recommended_merchant_id');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActiveFor($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}
