<?php

namespace App\Modules\PartnerOffers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOfferNetworkPublication extends Model
{
    protected $fillable = [
        'offer_id', 'network_id', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PartnerOffer::class, 'offer_id');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Network\Models\HyperlocalNetwork::class, 'network_id');
    }
}
