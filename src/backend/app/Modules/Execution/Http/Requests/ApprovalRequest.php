<?php

namespace App\Modules\Execution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the approval-request payload.
 *
 * Purpose: Generate a manager approval code for a pending high-value redemption.
 * Owner module: Execution
 */
class ApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'claim_token'    => ['required', 'string', 'max:20'],
            'partnership_id' => ['nullable', 'string', 'exists:partnerships,uuid'],
        ];
    }
}
