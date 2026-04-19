<?php

namespace App\Modules\Partnership\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return [
            'name'                                 => ['sometimes', 'string', 'max:255'],
            'start_at'                             => ['sometimes', 'nullable', 'date'],
            'end_at'                               => ['sometimes', 'nullable', 'date', 'after:start_at'],

            'terms'                                => ['sometimes', 'array'],
            'terms.per_bill_cap_amount'            => ['nullable', 'numeric', 'min:0'],
            'terms.per_bill_cap_percent'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms.min_bill_amount'                => ['nullable', 'numeric', 'min:0'],
            'terms.monthly_cap_amount'             => ['nullable', 'numeric', 'min:0'],
            'terms.partner_monthly_cap'            => ['nullable', 'numeric', 'min:0'],
            'terms.outlet_monthly_cap'             => ['nullable', 'numeric', 'min:0'],
            'terms.approval_mode'                  => ['nullable', 'integer', 'in:1,2,3'],
            'terms.approval_threshold'             => ['nullable', 'numeric', 'min:0'],
            // Points fields
            'terms.per_bill_cap_points'            => ['nullable', 'numeric', 'min:0'],
            'terms.min_bill_points'                => ['nullable', 'numeric', 'min:0'],
            'terms.monthly_cap_points'             => ['nullable', 'numeric', 'min:0'],
            // Daily cap fields
            'terms.daily_cap_amount'               => ['nullable', 'numeric', 'min:0'],
            'terms.daily_cap_points'               => ['nullable', 'numeric', 'min:0'],
            'terms.daily_transaction_count'        => ['nullable', 'integer', 'min:1'],
            'terms.outlet_daily_cap_amount'        => ['nullable', 'numeric', 'min:0'],
            'terms.outlet_daily_count'             => ['nullable', 'integer', 'min:1'],
            'terms.outlet_per_bill_cap_amount'     => ['nullable', 'numeric', 'min:0'],
            // Lifetime cap fields
            'terms.lifetime_cap_amount'            => ['nullable', 'numeric', 'min:0'],
            'terms.lifetime_cap_points'            => ['nullable', 'numeric', 'min:0'],
            // Notification and auto-pause flags
            'terms.notify_on_limit_hit'            => ['nullable', 'boolean'],
            'terms.notify_partner_on_limit_hit'    => ['nullable', 'boolean'],
            'terms.pause_on_monthly_limit'         => ['nullable', 'boolean'],

            'rules'                                => ['sometimes', 'array'],
            'rules.customer_type_rules'            => ['nullable', 'array'],
            'rules.inactivity_days'                => ['nullable', 'integer', 'min:1'],
            'rules.blackout_rules'                 => ['nullable', 'array'],
            'rules.time_band_rules'                => ['nullable', 'array'],
            'rules.stacking_rules'                 => ['nullable', 'array'],
            'rules.uses_per_customer'              => ['nullable', 'integer', 'min:1'],
            'rules.cooling_period_days'            => ['nullable', 'integer', 'min:1'],
            'rules.first_time_only'                => ['nullable', 'boolean'],
        ];
    }
}
