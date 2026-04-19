<?php

namespace App\Modules\Partnership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partnership\Models\PartnershipAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app alerts for partnership lifecycle events.
 *
 * GET    /api/partnership-alerts         — list unread alerts for current merchant
 * POST   /api/partnership-alerts/{id}/read — mark a single alert as read
 * POST   /api/partnership-alerts/read-all  — mark all as read
 *
 * Owner module: Partnership
 */
class PartnershipAlertController extends Controller
{
    /**
     * GET /api/partnership-alerts
     * Returns the 20 most recent alerts (read + unread) for the merchant.
     */
    public function index(Request $request): JsonResponse
    {
        $alerts = PartnershipAlert::where('recipient_merchant_id', $request->user()->merchant_id)
            ->with('partnership:id,uuid,name')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($a) => [
                'id'               => $a->id,
                'type'             => $a->type,
                'title'            => $a->title,
                'body'             => $a->body,
                'read'             => $a->read_at !== null,
                'created_at'       => $a->created_at->toIso8601String(),
                'partnership_uuid' => $a->partnership?->uuid,
                'partnership_name' => $a->partnership?->name,
            ]);

        $unreadCount = PartnershipAlert::where('recipient_merchant_id', $request->user()->merchant_id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'alerts'       => $alerts,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/partnership-alerts/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        PartnershipAlert::where('id', $id)
            ->where('recipient_merchant_id', $request->user()->merchant_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/partnership-alerts/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        PartnershipAlert::where('recipient_merchant_id', $request->user()->merchant_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
