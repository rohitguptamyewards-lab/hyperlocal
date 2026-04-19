<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\RoiService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AnalyticsController — read-only attribution, ROI, and trend endpoints.
 *
 * Owner module: Analytics
 * Reads: partner_attributions, partner_redemptions, partner_ledger_entries,
 *        campaigns, campaign_sends (via RoiService)
 * Writes: nothing
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly RoiService $roi) {}

    /**
     * GET /api/analytics/summary
     * Dashboard-level summary with financial metrics, trends, and per-partner breakdown.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            $this->roi->dashboardSummary($request->user()->merchant_id)
        );
    }

    /**
     * GET /api/analytics/trends?months=6
     * Dashboard-level monthly trend data for charts.
     */
    public function trends(Request $request): JsonResponse
    {
        $months = min((int) ($request->query('months', 6)), 24);

        return response()->json([
            'months'  => $months,
            'trend'   => $this->roi->dashboardTrend(
                $request->user()->merchant_id,
                $months,
            ),
        ]);
    }

    /**
     * GET /api/analytics/partnerships/{uuid}
     * Per-partnership ROI, retention, revenue, and trend breakdown.
     */
    public function partnership(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        return response()->json([
            'partnership_id' => $uuid,
            'analytics'      => $this->roi->forPartnership(
                $partnership->id,
                $request->user()->merchant_id,
            ),
        ]);
    }

    /**
     * GET /api/analytics/partnerships/{uuid}/trend?months=6
     * Per-partnership monthly trend for charts.
     */
    public function partnershipTrend(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $months = min((int) ($request->query('months', 6)), 24);

        return response()->json([
            'partnership_id' => $uuid,
            'months'         => $months,
            'trend'          => $this->roi->partnershipTrend(
                $partnership->id,
                $request->user()->merchant_id,
                $months,
            ),
        ]);
    }
}
