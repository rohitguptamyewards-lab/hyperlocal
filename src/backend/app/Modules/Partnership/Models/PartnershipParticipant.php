<?php

namespace App\Modules\Partnership\Models;

use App\Modules\Partnership\Constants\ParticipantRole;
use App\Models\Merchant;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PartnershipParticipant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'partnership_id', 'merchant_id', 'outlet_id', 'role',
        'approval_status', 'approved_by', 'approved_at',
        'suspended_at', 'suspension_reason',
        'issuing_enabled', 'redemption_enabled', 'campaigns_enabled', 'bill_offers_enabled',
        'offer_pos_type', 'offer_flat_amount', 'offer_percentage', 'offer_max_cap',
        'offer_min_bill', 'offer_monthly_cap', 'linked_offer_id', 'offer_filled',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'role'               => 'integer',
        'approval_status'    => 'integer',
        'approved_at'        => 'datetime',
        'suspended_at'       => 'datetime',
        'issuing_enabled'    => 'boolean',
        'redemption_enabled' => 'boolean',
        'campaigns_enabled'    => 'boolean',
        'bill_offers_enabled'  => 'boolean',
    ];

    /** True when this participant has suspended their side of the partnership */
    public function isSuspended(): bool
    {
        return !is_null($this->suspended_at);
    }

    /** True when either issuing or redemption is disabled */
    public function isPartiallyDisabled(): bool
    {
        return !$this->issuing_enabled || !$this->redemption_enabled;
    }

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

    /** True when outlet_id is NULL — means all outlets of this merchant */
    public function isBrandWide(): bool
    {
        return is_null($this->outlet_id);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ParticipantRole::APPROVAL_APPROVED;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', ParticipantRole::APPROVAL_APPROVED);
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }
}
