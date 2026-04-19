<?php

namespace App\Modules\EventTriggers\Models;

use Illuminate\Database\Eloquent\Model;

class EventLogEntry extends Model
{
    protected $table = 'event_log';

    protected $fillable = [
        'event_source_id', 'merchant_id', 'idempotency_key',
        'event_type', 'raw_payload', 'normalized_payload',
        'member_id', 'processing_status', 'action_outcome',
        'error_reason', 'received_at', 'processed_at',
    ];

    protected $casts = [
        'raw_payload'        => 'array',
        'normalized_payload' => 'array',
        'action_outcome'     => 'array',
        'received_at'        => 'datetime',
        'processed_at'       => 'datetime',
    ];
}
