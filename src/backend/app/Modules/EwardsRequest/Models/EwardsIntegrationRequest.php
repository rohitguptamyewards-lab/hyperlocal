<?php

namespace App\Modules\EwardsRequest\Models;

use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Merchant-initiated request to activate eWards integration.
 *
 * Lifecycle: pending → approved | rejected
 * Soft-deleted rejected rows allow re-application without losing history.
 *
 * Owner module: EwardsRequest
 * Table owned: ewards_integration_requests
 */
class EwardsIntegrationRequest extends Model
{
    use SoftDeletes;

    protected $table = 'ewards_integration_requests';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'status',
        'requested_by',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by');
    }
}
