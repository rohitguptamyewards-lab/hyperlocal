<?php

namespace App\Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * PartnerAttribution — first-visit record for a customer arriving via a partnership.
 *
 * Owner module: Analytics
 * Tables owned: partner_attributions
 * Created by: RecordFirstVisitAttribution listener (on RedemptionExecuted, customer_type=NEW)
 * Retention flags updated by: UpdateRetentionFlags scheduled command
 *
 * One row per redemption where customer was NEW at time of redemption.
 * Covers EXISTING + REACTIVATED too so ROI can be calculated across all types.
 */
class PartnerAttribution extends Model
{
    protected $table = 'partner_attributions';

    protected $fillable = [
        'partnership_id', 'redemption_id', 'customer_id',
        'source_merchant_id', 'target_merchant_id', 'outlet_id',
        'customer_type', 'benefit_amount', 'attributed_at', 'period_month',
        'retained_30d', 'retained_60d', 'retained_90d',
        'retained_30d_at', 'retained_60d_at', 'retained_90d_at',
    ];

    protected $casts = [
        'attributed_at'   => 'datetime',
        'retained_30d_at' => 'datetime',
        'retained_60d_at' => 'datetime',
        'retained_90d_at' => 'datetime',
        'period_month'    => 'date',
        'retained_30d'    => 'boolean',
        'retained_60d'    => 'boolean',
        'retained_90d'    => 'boolean',
        'benefit_amount'  => 'decimal:2',
    ];

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForPartnership(Builder $query, int $partnershipId): Builder
    {
        return $query->where('partnership_id', $partnershipId);
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where(function ($q) use ($merchantId) {
            $q->where('source_merchant_id', $merchantId)
              ->orWhere('target_merchant_id', $merchantId);
        });
    }

    public function scopeInPeriod(Builder $query, string $yearMonth): Builder
    {
        return $query->whereRaw("strftime('%Y-%m', period_month) = ?", [$yearMonth]);
    }
}
