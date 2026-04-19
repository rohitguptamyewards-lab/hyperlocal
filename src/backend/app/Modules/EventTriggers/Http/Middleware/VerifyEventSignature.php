<?php

namespace App\Modules\EventTriggers\Http\Middleware;

use App\Modules\EventTriggers\Models\EventSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies HMAC-SHA256 signatures for signed server-to-server event ingestion.
 * Reuses the same pattern as VerifyWebhookSignature.
 */
class VerifyEventSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchantKey = $request->header('X-Merchant-Key');
        $signature   = $request->header('X-Signature');
        $timestamp   = $request->header('X-Timestamp');

        if (!$merchantKey || !$signature) {
            return response()->json(['error' => 'Missing X-Merchant-Key or X-Signature header.'], 401);
        }

        $source = EventSource::where('merchant_key', $merchantKey)->where('status', 1)->first();
        if (!$source) {
            return response()->json(['error' => 'Invalid merchant key.'], 401);
        }

        // Verify timestamp freshness (5 min tolerance)
        if ($timestamp && abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Timestamp too old.'], 401);
        }

        // Verify HMAC
        $payload  = ($timestamp ?? '') . '.' . $request->getContent();
        $expected = hash_hmac('sha256', $payload, $source->merchant_secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        // Inject source into request for downstream use
        $request->merge(['_event_source' => $source]);

        return $next($request);
    }
}
