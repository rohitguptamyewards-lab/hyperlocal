<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public brand self-registration endpoint.
 * Creates a merchant (inactive, pending approval), first outlet, and admin user.
 *
 * Owner module: Registration
 * Integration points: merchants, outlets, users tables
 */
class BrandRegistrationController extends Controller
{
    /**
     * Register a new brand (merchant + outlet + admin user).
     *
     * POST /api/register-brand
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_name'    => ['required', 'string', 'max:200'],
            'category'      => ['required', 'string', 'max:100'],
            'city'          => ['required', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'gst_number'    => ['nullable', 'string', 'max:20'],
            'outlet_name'   => ['required', 'string', 'max:200'],
            'contact_name'  => ['required', 'string', 'max:200'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create merchant (inactive — needs super admin approval)
        $merchant = Merchant::create([
            'uuid'               => (string) Str::uuid(),
            'name'               => $data['brand_name'],
            'category'           => $data['category'],
            'city'               => $data['city'],
            'state'              => $data['state'] ?? null,
            'phone'              => $data['contact_phone'],
            'email'              => $data['contact_email'],
            'is_active'             => false,
            'open_to_partnerships'  => false,
            'ecosystem_active'      => false,
            'registration_status'   => 'pending',
        ]);

        // Create primary outlet
        Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'name'        => $data['outlet_name'],
            'city'        => $data['city'],
            'state'       => $data['state'] ?? null,
            'is_active'   => true,
        ]);

        // Create admin user
        User::create([
            'name'        => $data['contact_name'],
            'email'       => $data['contact_email'],
            'password'    => $data['password'],  // hashed via cast
            'merchant_id' => $merchant->id,
            'role'        => 1, // admin
        ]);

        return response()->json([
            'message' => 'Registration submitted. Our team will review and activate your account.',
        ], 201);
    }
}
