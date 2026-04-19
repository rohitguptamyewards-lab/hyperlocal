<?php

namespace App\Modules\EventTriggers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventSource extends Model
{
    protected $fillable = [
        'uuid', 'merchant_id', 'name', 'source_type',
        'merchant_key', 'merchant_secret', 'config',
        'status', 'test_mode', 'created_by',
    ];

    protected $casts = [
        'config'    => 'array',
        'status'    => 'integer',
        'test_mode' => 'boolean',
    ];

    protected $hidden = ['merchant_secret'];

    protected static function booted(): void
    {
        static::creating(static function (self $m): void {
            $m->uuid ??= (string) Str::uuid();
            $m->merchant_key ??= 'mk_' . Str::random(40);
            $m->merchant_secret ??= Str::random(48);
        });
    }

    public function merchant(): BelongsTo { return $this->belongsTo(\App\Models\Merchant::class); }
    public function triggers(): HasMany { return $this->hasMany(EventTrigger::class, 'event_source_id'); }
}
