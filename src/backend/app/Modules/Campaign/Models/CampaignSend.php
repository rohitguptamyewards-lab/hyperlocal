<?php

namespace App\Modules\Campaign\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Member\Models\Member;

/**
 * One row per campaign per member — idempotent send tracking.
 * Unique constraint on (campaign_id, member_id) prevents duplicate sends.
 *
 * Owner module: Campaign
 * Tables owned: campaign_sends
 */
class CampaignSend extends Model
{
    public const STATUS_PENDING   = 1;
    public const STATUS_SENT      = 2;
    public const STATUS_FAILED    = 3;
    public const STATUS_DELIVERED = 4;

    protected $fillable = [
        'campaign_id',
        'member_id',
        'status',
        'sent_at',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta'    => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
