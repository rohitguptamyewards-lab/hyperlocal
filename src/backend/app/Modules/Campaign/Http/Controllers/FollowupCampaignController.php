<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FollowupCampaignController — GAP 6: Follow-Up for Unredeemed/Expired Tokens
 *
 * Merchants configure automatic follow-up messages that fire when a customer's
 * partner token expires without being redeemed.
 *
 * Module: Campaign
 * Routes (all under auth:sanctum):
 *   GET  /api/followup-campaigns         — list merchant's campaigns
 *   POST /api/followup-campaigns         — create new campaign
 *   PUT  /api/followup-campaigns/{id}    — update / toggle
 *   GET  /api/followup-campaigns/stats   — expired unredeemed token count
 */
class FollowupCampaignController extends Controller
{
    /**
     * List the authenticated merchant's follow-up campaigns.
     *
     * GET /api/followup-campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $campaigns = DB::table('followup_campaigns as fc')
            ->leftJoin('partnerships as p', 'p.id', '=', 'fc.partnership_id')
            ->where('fc.merchant_id', $merchantId)
            ->orderByDesc('fc.created_at')
            ->get([
                'fc.id',
                'fc.trigger_type',
                'fc.delay_hours',
                'fc.message_template',
                'fc.is_active',
                'fc.sent_count',
                'fc.partnership_id',
                'p.name as partnership_name',
                'fc.created_at',
                'fc.updated_at',
            ]);

        return response()->json($campaigns->values());
    }

    /**
     * Create a new follow-up campaign.
     *
     * POST /api/followup-campaigns
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trigger_type'     => ['required', 'string', 'in:token_expired_unredeemed'],
            'delay_hours'      => ['required', 'integer', 'in:6,12,24,48'],
            'message_template' => ['required', 'string', 'max:1000'],
            'partnership_id'   => ['nullable', 'integer', 'exists:partnerships,id'],
        ]);

        $merchantId = $request->user()->merchant_id;

        $id = DB::table('followup_campaigns')->insertGetId([
            'merchant_id'      => $merchantId,
            'partnership_id'   => $data['partnership_id'] ?? null,
            'trigger_type'     => $data['trigger_type'],
            'delay_hours'      => $data['delay_hours'],
            'message_template' => $data['message_template'],
            'is_active'        => true,
            'sent_count'       => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $campaign = DB::table('followup_campaigns')->find($id);

        return response()->json($campaign, 201);
    }

    /**
     * Update an existing follow-up campaign (fields or active toggle).
     *
     * PUT /api/followup-campaigns/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $campaign = DB::table('followup_campaigns')
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->first();

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        $data = $request->validate([
            'trigger_type'     => ['sometimes', 'string', 'in:token_expired_unredeemed'],
            'delay_hours'      => ['sometimes', 'integer', 'in:6,12,24,48'],
            'message_template' => ['sometimes', 'string', 'max:1000'],
            'is_active'        => ['sometimes', 'boolean'],
            'partnership_id'   => ['sometimes', 'nullable', 'integer', 'exists:partnerships,id'],
        ]);

        if (empty($data)) {
            return response()->json($campaign);
        }

        $data['updated_at'] = now();

        DB::table('followup_campaigns')
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->update($data);

        return response()->json(DB::table('followup_campaigns')->find($id));
    }

    /**
     * Return stats relevant to follow-up campaigns.
     * Currently returns: expired_unredeemed_count — partner_claims with status='issued' and expires_at < now.
     *
     * GET /api/followup-campaigns/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        // Count tokens issued by this merchant that have expired without redemption.
        // partner_claims: merchant_id = issuer, status=1 means issued/unredeemed.
        $expiredUnredeemedCount = DB::table('partner_claims')
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->where('expires_at', '<', now())
            ->count();

        return response()->json([
            'expired_unredeemed_count' => $expiredUnredeemedCount,
        ]);
    }
}
