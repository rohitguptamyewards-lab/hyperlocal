<?php

namespace App\Modules\LoyaltyBridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Member\Models\Member;

/**
 * Local balance cache per member per merchant.
 * Source of truth at runtime — pulled from external on first contact,
 * updated on every earn/redeem, pushed back out to external provider.
 *
 * Owner module: LoyaltyBridge
 * Tables owned: member_loyalty_balances
 * Reads but does not write: members, merchants, merchant_integrations
 */
class MemberLoyaltyBalance extends Model
{
    protected $fillable = [
        'member_id',
        'merchant_id',
        'balance',
        'currency_type',
        'provider',
        'last_synced_at',
    ];

    protected $casts = [
        'balance'        => 'float',
        'last_synced_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
