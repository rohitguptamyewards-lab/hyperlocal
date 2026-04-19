<?php

namespace App\Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a local member to their identity on an external provider.
 *
 * Owner module: Member
 * Tables owned: member_integrations
 */
class MemberIntegration extends Model
{
    protected $fillable = [
        'member_id',
        'provider',
        'external_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
