<?php

namespace App\Modules\MerchantSettings\Models;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Versioned history of a merchant's loyalty point valuation.
 *
 * Purpose: convert point-denominated partnership terms to rupees.
 * Owner module: MerchantSettings
 * Tables owned: merchant_point_valuations
 *
 * Current value = latest row where effective_from <= NOW().
 * Never UPDATE or DELETE rows — always INSERT a new row to preserve history.
 */
class MerchantPointValuation extends Model
{
    protected $fillable = [
        'merchant_id',
        'rupees_per_point',
        'effective_from',
        'confirmed_by',
        'note',
    ];

    protected $casts = [
        'rupees_per_point' => 'decimal:4',
        'effective_from'   => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Convenience: points → rupees using this valuation */
    public function toRupees(float $points): float
    {
        return round($points * (float) $this->rupees_per_point, 2);
    }

    /** Get the active valuation for a merchant (null if none set) */
    public static function current(int $merchantId): ?self
    {
        return static::where('merchant_id', $merchantId)
            ->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first();
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }
}
