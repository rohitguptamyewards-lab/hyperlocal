<?php

namespace App\Modules\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipRules extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'partnership_id', 'merchant_id',
        'customer_type_rules', 'inactivity_days',
        'blackout_rules', 'time_band_rules', 'stacking_rules',
        'uses_per_customer', 'cooling_period_days', 'first_time_only',
        'version', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'customer_type_rules' => 'array',
        'blackout_rules'      => 'array',
        'time_band_rules'     => 'array',
        'stacking_rules'      => 'array',
        'first_time_only'     => 'boolean',
        'inactivity_days'     => 'integer',
        'uses_per_customer'   => 'integer',
        'cooling_period_days' => 'integer',
        'version'             => 'integer',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }
}
