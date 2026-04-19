<?php

namespace App\Modules\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lightweight in-app alert for partnership lifecycle events.
 *
 * Owner module: Partnership
 * Table: partnership_alerts
 */
class PartnershipAlert extends Model
{
    protected $table = 'partnership_alerts';

    public const TYPE_OFFER_FILLED     = 'offer_filled';
    public const TYPE_OFFER_UPDATED    = 'offer_updated';
    public const TYPE_TERMS_UPDATED    = 'terms_updated';   // either party changed terms → moves to Negotiating
    public const TYPE_PARTNER_ACCEPTED = 'partner_accepted';
    public const TYPE_PARTNER_REJECTED = 'partner_rejected';

    protected $fillable = [
        'partnership_id',
        'recipient_merchant_id',
        'type',
        'title',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
