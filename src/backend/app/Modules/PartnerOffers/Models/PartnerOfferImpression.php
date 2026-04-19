<?php

namespace App\Modules\PartnerOffers\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerOfferImpression extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'merchant_id', 'shown_at', 'session_id'];

    protected $casts = ['shown_at' => 'datetime'];
}
