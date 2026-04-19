<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Super admin authentication — login, logout, me.
 *
 * Tokens are scoped to the SuperAdmin model via Sanctum.
 * Device-based token rotation: old tokens for the same device name are revoked on login.
 *
 * Owner module: SuperAdmin
 * Tables read/write: super_admins, personal_access_tokens
 */
class SuperAdminAuthController extends Controller
{
    /**
     * POST /api/super-admin/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $admin = SuperAdmin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Create new token — keep old tokens alive so other browser sessions remain valid
        $device = ($request->device_name ?? 'super-admin-web') . '_' . time();
        $token = $admin->createToken($device)->plainTextToken;

        $admin->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $token,
            'admin' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    /**
     * POST /api/super-admin/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/super-admin/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('sanctum');

        return response()->json([
            'id'            => $admin->id,
            'name'          => $admin->name,
            'email'         => $admin->email,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
        ]);
    }
}
