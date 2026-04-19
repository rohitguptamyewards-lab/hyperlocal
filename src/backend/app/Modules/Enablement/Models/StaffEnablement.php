<?php

namespace App\Modules\Enablement\Models;

use App\Models\Merchant;
use App\Models\Outlet;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StaffEnablement — one row per outlet per partnership.
 *
 * Owner module: Enablement
 * Table:        partnership_staff_enablement
 *
 * Key fields:
 *   last_training_at      — when staff last received training/briefing
 *   last_used_at          — updated on every RedemptionExecuted at this outlet
 *   is_dormant            — true when no activity for DORMANCY_THRESHOLD_DAYS
 *   dormancy_alert_sent   — prevents repeat alerts per dormancy cycle; reset on recovery
 */
class StaffEnablement extends Model
{
    use SoftDeletes;

    protected $table = 'partnership_staff_enablement';

    protected $fillable = [
        'merchant_id',
        'partnership_id',
        'outlet_id',
        'last_training_at',
        'last_used_at',
        'is_dormant',
        'dormant_since',
        'dormancy_alert_sent',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_dormant'          => 'boolean',
        'dormancy_alert_sent' => 'boolean',
        'last_training_at'    => 'datetime',
        'last_used_at'        => 'datetime',
        'dormant_since'       => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeForPartnership($query, int $partnershipId)
    {
        return $query->where('partnership_id', $partnershipId);
    }
}
