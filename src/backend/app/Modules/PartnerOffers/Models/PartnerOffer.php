<?php

namespace App\Modules\PartnerOffers\Models;

use App\Modules\PartnerOffers\Constants\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PartnerOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'merchant_id', 'title', 'description', 'coupon_code',
        'discount_type', 'discount_value', 'image_url', 'expiry_date',
        'terms_conditions', 'display_template', 'status',
        'max_issuance', 'max_redemptions', 'pos_redemption_type',
        'flat_discount_amount', 'discount_percentage', 'max_cap_amount',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'discount_type'         => 'integer',
        'discount_value'        => 'float',
        'status'                => 'integer',
        'expiry_date'           => 'date',
        'max_issuance'          => 'integer',
        'max_redemptions'       => 'integer',
        'flat_discount_amount'  => 'float',
        'discount_percentage'   => 'float',
        'max_cap_amount'        => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Merchant::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PartnerOfferAttachment::class, 'offer_id');
    }

    public function networkPublications(): HasMany
    {
        return $this->hasMany(PartnerOfferNetworkPublication::class, 'offer_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', OfferStatus::ACTIVE);
    }

    public function scopeNotExpired(Builder $q): Builder
    {
        return $q->where(fn ($q2) => $q2->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()));
    }

    public function scopeForMerchant(Builder $q, int $merchantId): Builder
    {
        return $q->where('merchant_id', $merchantId);
    }
}
