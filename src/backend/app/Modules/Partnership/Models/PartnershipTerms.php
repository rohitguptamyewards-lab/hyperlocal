<?php

namespace App\Modules\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipTerms extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'partnership_id', 'merchant_id',
        'per_bill_cap_amount', 'per_bill_cap_percent', 'min_bill_amount',
        'monthly_cap_amount', 'partner_monthly_cap', 'outlet_monthly_cap',
        'approval_mode', 'approval_threshold', 'version',
        'per_bill_cap_points', 'monthly_cap_points', 'min_bill_points',
        'rupees_per_point_at_agreement',
        'daily_cap_amount', 'daily_cap_points', 'daily_transaction_count',
        'outlet_daily_cap_amount', 'outlet_daily_count', 'outlet_per_bill_cap_amount',
        'lifetime_cap_amount', 'lifetime_cap_points',
        'notify_on_limit_hit', 'notify_partner_on_limit_hit', 'pause_on_monthly_limit',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'per_bill_cap_amount'           => 'float',
        'per_bill_cap_percent'          => 'float',
        'min_bill_amount'               => 'float',
        'monthly_cap_amount'            => 'float',
        'partner_monthly_cap'           => 'float',
        'outlet_monthly_cap'            => 'float',
        'approval_threshold'            => 'float',
        'approval_mode'                 => 'integer',
        'version'                       => 'integer',
        'per_bill_cap_points'           => 'float',
        'monthly_cap_points'            => 'float',
        'min_bill_points'               => 'float',
        'rupees_per_point_at_agreement' => 'float',
        // Daily / lifetime cap amounts
        'daily_cap_amount'              => 'float',
        'daily_cap_points'              => 'float',
        'outlet_daily_cap_amount'       => 'float',
        'outlet_per_bill_cap_amount'    => 'float',
        'lifetime_cap_amount'           => 'float',
        'lifetime_cap_points'           => 'float',
        // Daily count integers
        'daily_transaction_count'       => 'integer',
        'outlet_daily_count'            => 'integer',
        // Notification / auto-pause flags
        'notify_on_limit_hit'           => 'boolean',
        'notify_partner_on_limit_hit'   => 'boolean',
        'pause_on_monthly_limit'        => 'boolean',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }
}
