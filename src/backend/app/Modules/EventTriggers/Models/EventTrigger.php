<?php

namespace App\Modules\EventTriggers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventTrigger extends Model
{
    protected $fillable = [
        'uuid', 'merchant_id', 'event_source_id', 'name',
        'event_type', 'condition_json', 'action_type', 'action_config_json',
        'partnership_id', 'offer_id', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'condition_json'     => 'array',
        'action_config_json' => 'array',
        'is_active'          => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $m): void {
            $m->uuid ??= (string) Str::uuid();
        });
    }

    public function merchant(): BelongsTo { return $this->belongsTo(\App\Models\Merchant::class); }
    public function source(): BelongsTo { return $this->belongsTo(EventSource::class, 'event_source_id'); }
}
