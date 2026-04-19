<?php

namespace App\Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks which merchants belong to which hyperlocal networks.
 *
 * Statuses: 1=active, 2=suspended, 3=left
 *
 * A merchant can be a member of multiple networks simultaneously.
 * Caps and calculation isolation are per partnership_id, not per network,
 * so cross-network membership is safe by design.
 *
 * Owner module: Network
 * Table owned: network_memberships
 */
class NetworkMembership extends Model
{
    protected $table = 'network_memberships';

    public const STATUS_ACTIVE    = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_LEFT      = 3;

    protected $fillable = [
        'network_id',
        'merchant_id',
        'status',
        'invited_by',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(HyperlocalNetwork::class, 'network_id');
    }
}
