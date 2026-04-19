<?php

namespace App\Modules\EventTriggers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EventTriggers\Services\EventIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) event ingestion endpoints.
 * Pixel, trigger URL, signed API, Shopify/WooCommerce webhooks.
 */
class EventIngestionController extends Controller
{
    public function __construct(private readonly EventIngestionService $ingestion) {}

    /**
     * GET /api/events/pixel/{merchantKey}
     * Lightweight pixel/img trigger — fires from a thank-you page.
     */
    public function pixel(Request $request, string $merchantKey): JsonResponse
    {
        $result = $this->ingestion->ingest($merchantKey, $request->query(), 'website');
        return response()->json($result, $result['status'] === 'rejected' ? 400 : 200);
    }

    /**
     * POST /api/events/trigger
     * Simple POST trigger — no signature required, uses X-Merchant-Key header.
     */
    public function trigger(Request $request): JsonResponse
    {
        $merchantKey = $request->header('X-Merchant-Key');
        if (!$merchantKey) {
            return response()->json(['error' => 'Missing X-Merchant-Key header.'], 400);
        }

        $result = $this->ingestion->ingest($merchantKey, $request->all(), 'website');
        return response()->json($result, $result['status'] === 'rejected' ? 400 : 200);
    }

    /**
     * POST /api/events/ingest
     * Signed server-to-server ingestion (verify_event middleware handles auth).
     */
    public function ingest(Request $request): JsonResponse
    {
        $source = $request->input('_event_source');
        $result = $this->ingestion->ingest($source->merchant_key, $request->except('_event_source'), 'api');
        return response()->json($result);
    }

    /**
     * POST /api/connectors/shopify/{merchantKey}/orders
     * Shopify order/create webhook.
     */
    public function shopifyOrders(Request $request, string $merchantKey): JsonResponse
    {
        $result = $this->ingestion->ingest($merchantKey, $request->all(), 'shopify');
        return response()->json($result);
    }

    /**
     * POST /api/connectors/woocommerce/{merchantKey}/orders
     * WooCommerce order webhook.
     */
    public function woocommerceOrders(Request $request, string $merchantKey): JsonResponse
    {
        $result = $this->ingestion->ingest($merchantKey, $request->all(), 'woocommerce');
        return response()->json($result);
    }
}
