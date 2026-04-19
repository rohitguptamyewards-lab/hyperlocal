<?php

namespace App\Modules\SuperAdmin\Http\Middleware;

use App\Models\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SuperAdminAuth — guards all super admin routes.
 *
 * Purpose: Explicitly verify the authenticated token belongs to a SuperAdmin
 * model, not a merchant user. Prevents cross-guard token bleed where a
 * merchant Sanctum token could resolve through auth:sanctum on SA routes.
 *
 * ALWAYS use this middleware on super admin routes — never auth:sanctum alone.
 * Even if auth:sanctum resolves successfully, this middleware rejects non-SA users.
 *
 * Owner module: SuperAdmin
 */
class SuperAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Let Sanctum resolve the token first
        $user = $request->user('sanctum');

        if (!$user || !($user instanceof SuperAdmin)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
