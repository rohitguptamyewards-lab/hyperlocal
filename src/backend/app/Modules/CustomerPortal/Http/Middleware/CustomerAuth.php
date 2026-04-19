<?php

namespace App\Modules\CustomerPortal\Http\Middleware;

use App\Modules\CustomerPortal\Services\OtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies customer session token from Authorization header.
 * Injects member_id into the request for downstream controllers.
 *
 * Owner module: CustomerPortal
 */
class CustomerAuth
{
    public function __construct(private readonly OtpService $otp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Customer session token required.'], 401);
        }

        $memberId = $this->otp->resolveSession($token);

        if (!$memberId) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        $request->merge(['customer_member_id' => $memberId]);

        return $next($request);
    }
}
