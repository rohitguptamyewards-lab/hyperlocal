<?php

namespace App\Modules\PartnerOffers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOfferAttachment extends Model
{
    protected $fillable = [
        'offer_id', 'partnership_id', 'attached_by_merchant_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PartnerOffer::class, 'offer_id');
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Partnership\Models\Partnership::class);
    }
}
