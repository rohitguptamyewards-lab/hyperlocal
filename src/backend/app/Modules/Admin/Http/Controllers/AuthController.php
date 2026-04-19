<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Http\Requests\LoginRequest;
use App\Modules\Admin\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and return a Sanctum token.
     * For standalone dev use only — production will use eWards user management.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => $data['password'],
            'merchant_id' => $data['merchant_id'],
            'outlet_id'   => $data['outlet_id'] ?? null,
            'role'        => $data['role'] ?? 1,
        ]);

        $token = $user->createToken(
            $data['device_name'] ?? 'api'
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ], 201);
    }

    /**
     * Authenticate and return a Sanctum token.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create a new token — keep existing tokens alive so other sessions
        // (other browsers / tabs) remain authenticated.
        $token = $user->createToken(
            ($data['device_name'] ?? 'api') . '_' . time()
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /**
     * Revoke the current token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    // -------------------------------------------------------------------------

    private function userPayload(User $user): array
    {
        $payload = [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role,
            'merchant_id' => $user->merchant_id,
            'outlet_id'   => $user->outlet_id,
        ];

        if ($user->merchant_id) {
            $merchant = \App\Models\Merchant::find($user->merchant_id);
            if ($merchant) {
                $payload['merchant'] = [
                    'id'                  => $merchant->id,
                    'name'                => $merchant->name,
                    'city'                => $merchant->city,
                    'category'            => $merchant->category,
                    'is_active'           => $merchant->is_active,
                    'registration_status' => $merchant->registration_status,
                    'rejection_reason'    => $merchant->rejection_reason,
                ];
            }
        }

        return $payload;
    }
}
