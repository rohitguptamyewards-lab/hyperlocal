<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DeliveryStatsController — WhatsApp delivery status reporting (GAP 2).
 *
 * Purpose: Give merchants visibility into token delivery health.
 * Owner module: Execution
 * Tables: partner_claims (reads delivery_status, fallback_sms_sent columns)
 * Auth: auth:sanctum (merchant-scoped — each merchant sees only their own claims)
 */
class DeliveryStatsController extends Controller
{
    /**
     * GET /api/delivery/stats
     *
     * Returns aggregate delivery statistics for the authenticated merchant's
     * issued partner-claim tokens.
     *
     * Response shape:
     *   total_issued        — total claims for this merchant
     *   delivered           — status = 'delivered' or 'read'
     *   sent                — status = 'sent' (dispatched, not yet confirmed)
     *   failed              — status = 'failed'
     *   pending             — status = 'pending'
     *   no_whatsapp         — status = 'no_whatsapp' (no WA account on number)
     *   delivery_rate_percent — (delivered / total_issued) * 100, rounded to 1dp
     *   fallback_sms_count  — claims where fallback_sms_sent = true
     */
    public function stats(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $rows = DB::table('partner_claims')
            ->where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*)                                                    AS total_issued,
                SUM(delivery_status IN ('delivered','read'))                AS delivered,
                SUM(delivery_status = 'sent')                               AS sent,
                SUM(delivery_status = 'failed')                             AS failed,
                SUM(delivery_status = 'pending')                            AS pending,
                SUM(delivery_status = 'no_whatsapp')                        AS no_whatsapp,
                SUM(fallback_sms_sent = 1)                                  AS fallback_sms_count
            ")
            ->first();

        $totalIssued  = (int) ($rows->total_issued ?? 0);
        $delivered    = (int) ($rows->delivered    ?? 0);
        $deliveryRate = $totalIssued > 0
            ? round(($delivered / $totalIssued) * 100, 1)
            : 0.0;

        return response()->json([
            'total_issued'          => $totalIssued,
            'delivered'             => $delivered,
            'sent'                  => (int) ($rows->sent            ?? 0),
            'failed'                => (int) ($rows->failed          ?? 0),
            'pending'               => (int) ($rows->pending         ?? 0),
            'no_whatsapp'           => (int) ($rows->no_whatsapp     ?? 0),
            'delivery_rate_percent' => $deliveryRate,
            'fallback_sms_count'    => (int) ($rows->fallback_sms_count ?? 0),
        ]);
    }

    /**
     * GET /api/delivery/failures
     *
     * Returns the 20 most recent failed or no_whatsapp tokens for this merchant.
     * Used to surface actionable items (e.g. wrong phone, WhatsApp not registered).
     *
     * Response shape (array):
     *   [{ customer_phone, token, delivery_status, partnership_name, issued_at }]
     */
    public function recentFailures(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $failures = DB::table('partner_claims as pc')
            ->join('partnerships as p', 'p.id', '=', 'pc.partnership_id')
            ->where('pc.merchant_id', $merchantId)
            ->whereNull('pc.deleted_at')
            ->whereIn('pc.delivery_status', ['failed', 'no_whatsapp'])
            ->orderByDesc('pc.issued_at')
            ->limit(20)
            ->select([
                'pc.customer_phone',
                'pc.token',
                'pc.delivery_status',
                'p.name as partnership_name',
                'pc.issued_at',
            ])
            ->get()
            ->map(fn ($row) => [
                'customer_phone'   => $row->customer_phone,
                'token'            => $row->token,
                'delivery_status'  => $row->delivery_status,
                'partnership_name' => $row->partnership_name,
                'issued_at'        => $row->issued_at,
            ]);

        return response()->json($failures);
    }
}
