<?php

namespace App\Modules\Network\Models;

use App\Models\Merchant;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP 7 — Partner Rating / Trust System
 *
 * Represents a rating (1–5) given by one merchant to their partner
 * after experiencing a live/paused partnership.
 */
class PartnerRating extends Model
{
    protected $fillable = [
        'partnership_id',
        'rated_by_merchant_id',
        'rated_merchant_id',
        'rating',
        'review_text',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'rated_by_merchant_id');
    }

    public function ratedMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'rated_merchant_id');
    }
}
