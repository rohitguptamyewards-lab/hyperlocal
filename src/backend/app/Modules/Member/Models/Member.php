<?php

namespace App\Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Member — own customer identity record.
 *
 * Owner module: Member
 * Tables owned: members
 * Integration points: partner_claims.member_id, partner_redemptions.member_id,
 *   campaign_sends.member_id, member_integrations.member_id,
 *   member_loyalty_balances.member_id
 */
class Member extends Model
{
    protected $fillable = [
        'uuid',
        'phone',
        'name',
        'email',
        'whatsapp_opt_in',
        'last_seen_at',
    ];

    protected $casts = [
        'whatsapp_opt_in' => 'boolean',
        'last_seen_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(MemberIntegration::class);
    }

    public function loyaltyBalances(): HasMany
    {
        return $this->hasMany(MemberLoyaltyBalance::class);
    }
}
