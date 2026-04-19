<?php

namespace App\Modules\Execution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // partnership_id and outlet_id are auto-resolved from the claim when not provided.
            'partnership_id' => ['nullable', 'string', 'exists:partnerships,uuid'],
            'outlet_id'      => ['nullable', 'integer', 'exists:outlets,id'],
            'claim_token'    => ['required', 'string', 'max:20'],
            'bill_amount'    => ['required', 'numeric', 'min:0.01'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'bill_id'        => ['nullable', 'string', 'max:100'],
            'customer_id'    => ['nullable', 'integer'],
            'approval_code'  => ['nullable', 'string', 'size:6'],
        ];
    }
}
