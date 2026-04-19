<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outlet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'merchant_id', 'name', 'address', 'city',
        'state', 'pincode', 'latitude', 'longitude', 'phone', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
