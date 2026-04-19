<?php

namespace App\Modules\CustomerPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\OtpService;
use App\Modules\Member\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Customer OTP login flow.
 *
 * POST /api/customer/send-otp   → generates OTP, sends via WhatsApp (mock in dev)
 * POST /api/customer/verify-otp → verifies OTP, returns session token
 *
 * Owner module: CustomerPortal
 */
class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly OtpService     $otp,
        private readonly MemberService  $members,
    ) {}

    /**
     * Send OTP to phone number via WhatsApp.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $phone = $this->members->normalise($data['phone']);

        try {
            $otpCode = $this->otp->generate($phone);

            // In production: send via WhatsApp using platform credit pool
            // For now: log the OTP (WHATSAPP_DRIVER=mock)
            Log::info('CustomerPortal OTP', ['phone' => $phone, 'otp' => $otpCode]);

            $response = ['message' => 'OTP sent to your WhatsApp.'];

            // DEV ONLY: include OTP in response for local testing.
            // APP_DEBUG=false in production ensures this key is never present.
            if (config('app.debug')) {
                $response['dev_otp'] = $otpCode;
            }

            return response()->json($response);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
    }

    /**
     * Verify OTP and return session token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $phone = $this->members->normalise($data['phone']);

        if (!$this->otp->verify($phone, $data['otp'])) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Find or create member
        $member = $this->members->findOrCreateByPhone($phone);

        // Create session token
        $token = $this->otp->createSessionToken($member->id);

        return response()->json([
            'token'  => $token,
            'member' => [
                'id'    => $member->id,
                'name'  => $member->name,
                'phone' => $member->phone,
            ],
        ]);
    }
}
