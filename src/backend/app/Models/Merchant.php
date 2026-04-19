<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'category', 'city', 'state',
        'pincode', 'phone', 'email', 'is_active', 'open_to_partnerships', 'ecosystem_active',
        'trust_score', 'registration_status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'open_to_partnerships' => 'boolean',
        'ecosystem_active'     => 'boolean',  // E-001
        'trust_score'          => 'decimal:2',
        'reviewed_at'          => 'datetime',
    ];

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
