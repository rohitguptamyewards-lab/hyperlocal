<?php

namespace App\Modules\CustomerActivation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partnership_uuid'  => ['required', 'string', 'exists:partnerships,uuid'],
            'source_outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'target_outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'customer_phone'   => ['nullable', 'string', 'max:20'],
        ];
    }
}
