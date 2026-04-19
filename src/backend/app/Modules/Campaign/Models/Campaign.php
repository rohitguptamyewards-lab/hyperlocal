<?php

namespace App\Modules\Campaign\Models;

use App\Modules\Campaign\Constants\CampaignTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Merchant-created WhatsApp broadcast campaign.
 * V1: template_key is fixed — no custom message bodies allowed.
 *
 * Owner module: Campaign
 * Tables owned: campaigns
 * Fires: campaign_sends rows via DispatchCampaignSends job
 */
class Campaign extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 1;
    public const STATUS_SCHEDULED = 2;
    public const STATUS_RUNNING   = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_CANCELLED = 5;

    protected $fillable = [
        'uuid',
        'merchant_id',
        'name',
        'template_key',
        'target_segment',
        'template_vars',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'target_segment' => 'array',
        'template_vars'  => 'array',
        'scheduled_at'   => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    /**
     * Returns the human-readable template label for display.
     */
    public function templateLabel(): string
    {
        return CampaignTemplate::label($this->template_key);
    }
}
