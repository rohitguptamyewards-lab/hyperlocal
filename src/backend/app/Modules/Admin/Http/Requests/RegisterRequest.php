<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'confirmed', Password::min(8)],
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'outlet_id'   => ['nullable', 'integer', 'exists:outlets,id'],
            'role'        => ['nullable', 'integer', 'in:1,2,3'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
