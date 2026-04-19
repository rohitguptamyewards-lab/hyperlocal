<?php

namespace App\Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation to join a hyperlocal network.
 *
 * Channels: email | whatsapp | link
 * Statuses: pending | accepted | expired | cancelled
 *
 * Invitation tokens are URL-safe random strings (64 chars).
 * On acceptance: a NetworkMembership row is created and this row is updated to 'accepted'.
 *
 * Owner module: Network
 * Table owned: network_invitations
 */
class NetworkInvitation extends Model
{
    protected $table = 'network_invitations';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'network_id',
        'invited_by',
        'invite_channel',
        'contact',
        'token',
        'status',
        'max_uses',
        'uses_count',
        'merchant_id',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
            'max_uses'    => 'integer',
            'uses_count'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid  ??= (string) Str::uuid();
            $model->token ??= Str::random(64);
        });
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(HyperlocalNetwork::class, 'network_id');
    }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    public function isExpired(): bool
    {
        return in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_ACCEPTED, self::STATUS_CANCELLED], true)
            || !$this->hasRemainingUses()
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function hasRemainingUses(): bool
    {
        return $this->max_uses === null || $this->uses_count < $this->max_uses;
    }
}
