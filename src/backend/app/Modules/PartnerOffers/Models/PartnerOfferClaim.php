<?php

namespace App\Modules\PartnerOffers\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerOfferClaim extends Model
{
    public $timestamps = false;

    protected $fillable = ['offer_id', 'merchant_id', 'customer_phone', 'claimed_at'];

    protected $casts = ['claimed_at' => 'datetime'];
}
