<?php

namespace App\Modules\RulesEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cap counter row — one per merchant+partnership+outlet+period.
 * outlet_id = NULL is the global (partnership-level) counter.
 *
 * NEVER update this model outside of CapEnforcementService.
 * All writes must happen inside a DB::transaction with lockForUpdate().
 */
class CapCounter extends Model
{
    public $timestamps = true;

    protected $table = 'partnership_cap_counters';

    protected $fillable = [
        'merchant_id', 'partnership_id', 'outlet_id',
        'period_year', 'period_month',
        'amount_used', 'redemption_count',
    ];

    protected $casts = [
        'amount_used'       => 'float',
        'redemption_count'  => 'integer',
        'period_year'       => 'integer',
        'period_month'      => 'integer',
    ];

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopePartnershipLevel(Builder $query, int $partnershipId, int $merchantId): Builder
    {
        return $query
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->whereNull('outlet_id');
    }

    public function scopeOutletLevel(Builder $query, int $partnershipId, int $merchantId, int $outletId): Builder
    {
        return $query
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->where('outlet_id', $outletId);
    }
}
