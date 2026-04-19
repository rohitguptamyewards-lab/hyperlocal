<?php

namespace App\Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A merchant-owned hyperlocal network — a named group of merchants
 * that agree to cross-promote each other within a geographic area.
 *
 * Statuses: 1=active, 2=suspended, 3=closed
 *
 * Owner module: Network
 * Table owned: hyperlocal_networks
 * Reads: merchants, users
 */
class HyperlocalNetwork extends Model
{
    protected $table = 'hyperlocal_networks';

    /** @var int */
    public const STATUS_ACTIVE    = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_CLOSED    = 3;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'owner_merchant_id',
        'status',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
            $model->slug ??= Str::slug($model->name) . '-' . Str::random(4);
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(NetworkMembership::class, 'network_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(NetworkInvitation::class, 'network_id');
    }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
}
