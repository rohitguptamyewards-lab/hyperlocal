<?php

namespace App\Modules\Webhook\Http\Middleware;

use App\Modules\Webhook\Services\WebhookSignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: verifies HMAC-SHA256 webhook signatures before passing to controller.
 *
 * Usage in route: ->middleware('webhook.verify:ewrds')
 * The source name (e.g. 'ewrds') is passed as a middleware parameter and
 * must match a row in webhook_signing_keys.
 *
 * Required headers:
 *   X-Signature: sha256=<hex>
 *   X-Timestamp: <unix_timestamp>    (optional but strongly recommended)
 *   X-Nonce: <unique_string>          (optional — enables replay protection)
 *
 * Returns 401 on any verification failure.
 *
 * Owner module: Webhook
 * Depends on: WebhookSignatureService
 */
class VerifyWebhookSignature
{
    public function __construct(
        private readonly WebhookSignatureService $signer,
    ) {}

    /**
     * @param  Request $request
     * @param  Closure $next
     * @param  string  $source  Source name from route middleware parameter
     */
    public function handle(Request $request, Closure $next, string $source = 'ewrds'): Response
    {
        $signature = $request->header('X-Signature');

        if (!$signature) {
            return response()->json(['error' => 'Missing X-Signature header.'], 401);
        }

        $rawBody   = $request->getContent();
        $timestamp = $request->header('X-Timestamp');
        $nonce     = $request->header('X-Nonce');

        try {
            $valid = $this->signer->verify(
                source:    $source,
                rawBody:   $rawBody,
                signature: $signature,
                timestamp: $timestamp,
                nonce:     $nonce,
            );
        } catch (\RuntimeException $e) {
            // No active key configured — log and reject
            Log::error('Webhook signature key missing.', [
                'source' => $source,
                'error'  => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Webhook source not configured.'], 500);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if (!$valid) {
            Log::warning('Webhook signature verification failed.', [
                'source'    => $source,
                'ip'        => $request->ip(),
                'timestamp' => $timestamp,
            ]);
            return response()->json(['error' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
