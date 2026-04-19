<?php

namespace App\Modules\Partnership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AnnouncementController — GAP 3: Auto Partnership Announcement to Existing Customers
 *
 * Allows a merchant to preview, send, and review history of partnership announcements
 * targeted at their own customer base (merchant_customers table).
 *
 * Module: Partnership
 * Routes:
 *   POST /api/partnerships/{uuid}/announcements/preview
 *   POST /api/partnerships/{uuid}/announcements/send
 *   GET  /api/partnerships/{uuid}/announcements
 */
class AnnouncementController extends Controller
{
    /**
     * Preview an announcement without persisting it.
     * Returns the recipient count (merchant's own customer list size).
     *
     * POST /api/partnerships/{uuid}/announcements/preview
     */
    public function preview(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $merchantId     = $request->user()->merchant_id;
        $recipientCount = DB::table('merchant_customers')
            ->where('merchant_id', $merchantId)
            ->count();

        return response()->json([
            'recipient_count' => $recipientCount,
            'message_preview' => $request->input('message'),
        ]);
    }

    /**
     * Persist and "send" the announcement.
     * Saves a record in partnership_announcements (status=sent) and returns recipient_count.
     *
     * In production this would dispatch an async job to deliver messages via WhatsApp/SMS.
     * For now the record is saved and a success response is returned immediately.
     *
     * POST /api/partnerships/{uuid}/announcements/send
     */
    public function send(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $merchantId     = $request->user()->merchant_id;
        $recipientCount = DB::table('merchant_customers')
            ->where('merchant_id', $merchantId)
            ->count();

        $id = DB::table('partnership_announcements')->insertGetId([
            'partnership_id'     => $partnership->id,
            'sent_by_merchant_id' => $merchantId,
            'message_text'       => $data['message'],
            'recipient_count'    => $recipientCount,
            'status'             => 'sent',
            'sent_at'            => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // TODO: Dispatch async job to broadcast via WhatsApp/SMS gateway when wired.
        \Illuminate\Support\Facades\Log::info('Partnership announcement queued', [
            'announcement_id' => $id,
            'partnership_uuid' => $uuid,
            'merchant_id'      => $merchantId,
            'recipient_count'  => $recipientCount,
        ]);

        return response()->json([
            'success'         => true,
            'recipient_count' => $recipientCount,
            'announcement_id' => $id,
        ], 201);
    }

    /**
     * List past announcements for this partnership sent by the authenticated merchant.
     *
     * GET /api/partnerships/{uuid}/announcements
     */
    public function history(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $merchantId = $request->user()->merchant_id;

        $announcements = DB::table('partnership_announcements')
            ->where('partnership_id', $partnership->id)
            ->where('sent_by_merchant_id', $merchantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'message_text', 'recipient_count', 'status', 'scheduled_at', 'sent_at', 'created_at']);

        return response()->json($announcements->values());
    }
}
