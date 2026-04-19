<?php

namespace App\Modules\PartnerOffers\Http\Requests;

use App\Modules\PartnerOffers\Constants\OfferDisplayTemplate;
use Illuminate\Foundation\Http\FormRequest;

class CreateOfferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'                => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'coupon_code'          => ['required', 'string', 'max:50'],
            'discount_type'        => ['nullable', 'integer', 'in:1,2'],
            'discount_value'       => ['nullable', 'numeric', 'min:0.01'],
            'image_url'            => ['nullable', 'string', 'max:500'],
            'expiry_date'          => ['nullable', 'date', 'after_or_equal:today'],
            'terms_conditions'     => ['nullable', 'string', 'max:5000'],
            'display_template'     => ['nullable', 'string', 'in:' . implode(',', OfferDisplayTemplate::VALID_KEYS)],
            // eWards-style fields
            'max_issuance'         => ['nullable', 'integer', 'min:1'],
            'max_redemptions'      => ['nullable', 'integer', 'min:1'],
            'pos_redemption_type'  => ['nullable', 'string', 'in:flat,percentage'],
            'flat_discount_amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:pos_redemption_type,flat'],
            'discount_percentage'  => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:pos_redemption_type,percentage'],
            'max_cap_amount'       => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
