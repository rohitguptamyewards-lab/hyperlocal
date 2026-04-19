<?php

namespace App\Modules\Partnership\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return [
            'name'                        => ['required', 'string', 'max:255'],
            'scope_type'                  => ['required', 'integer', 'in:1,2'],
            'partner_merchant_id'         => ['required', 'integer', 'exists:merchants,id'],
            'start_at'                    => ['nullable', 'date', 'after_or_equal:today'],
            'end_at'                      => ['nullable', 'date', 'after:start_at'],

            // Participants (outlets for outlet-level partnerships)
            'proposer_outlet_ids'         => ['nullable', 'array'],
            'proposer_outlet_ids.*'       => ['integer', 'exists:outlets,id'],
            'acceptor_outlet_ids'         => ['nullable', 'array'],
            'acceptor_outlet_ids.*'       => ['integer', 'exists:outlets,id'],

            // Offer structure
            'offer_structure'                      => ['nullable', 'string', 'in:same,different'],
            'proposer_offer'                       => ['nullable', 'array'],
            'proposer_offer.pos_type'              => ['nullable', 'string', 'in:flat,percentage'],
            'proposer_offer.flat_amount'           => ['nullable', 'numeric', 'min:0'],
            'proposer_offer.percentage'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'proposer_offer.max_cap'               => ['nullable', 'numeric', 'min:0'],
            'proposer_offer.min_bill'              => ['nullable', 'numeric', 'min:0'],
            'proposer_offer.monthly_cap'           => ['nullable', 'numeric', 'min:0'],
            'proposer_offer.linked_offer_id'       => ['nullable', 'integer', 'exists:partner_offers,id'],

            // Terms (optional at creation — can be set during negotiation)
            'terms'                                => ['nullable', 'array'],
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
        ];
    }

    public function messages(): array
    {
        return [
            'partner_merchant_id.exists' => 'The selected partner merchant does not exist.',
            'proposer_outlet_ids.*.exists' => 'One or more selected proposer outlets do not exist.',
            'acceptor_outlet_ids.*.exists' => 'One or more selected acceptor outlets do not exist.',
            'end_at.after' => 'End date must be after start date.',
        ];
    }
}
