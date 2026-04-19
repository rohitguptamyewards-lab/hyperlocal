<?php

namespace App\Modules\Webhook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Inbound HTTP webhook endpoints for ecosystem lifecycle events.
 *
 * All routes protected by VerifyWebhookSignature middleware.
 *
 * POST /api/webhooks/ecosystem/merchant-exit
 *   Triggered when a merchant leaves the eWards ecosystem.
 *   Suspends all LIVE partnerships the merchant participates in,
 *   sets merchants.ecosystem_active = false.
 *
 * POST /api/webhooks/ecosystem/merchant-reactivate
 *   Triggered when a merchant re-joins. Sets ecosystem_active = true.
 *   Does NOT auto-resume partnerships — super admin must do that manually.
 *
 * Owner module: Webhook
 * Tables written: merchants (ecosystem_active), partnerships (status)
 * Reads: partnership_participants
 */
class EcosystemWebhookController extends Controller
{
    /**
     * POST /api/webhooks/ecosystem/merchant-exit
     *
     * Body: { merchant_id: int }
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function merchantExit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
        ]);

        $merchantId = (int) $data['merchant_id'];

        DB::transaction(function () use ($merchantId): void {
            // Mark merchant as ecosystem-inactive
            DB::table('merchants')
                ->where('id', $merchantId)
                ->update([
                    'ecosystem_active' => false,
                    'updated_at'       => now(),
                ]);

            // Suspend all LIVE partnerships this merchant participates in
            $partnershipIds = DB::table('partnership_participants')
                ->where('merchant_id', $merchantId)
                ->whereNull('deleted_at')
                ->pluck('partnership_id');

            if ($partnershipIds->isNotEmpty()) {
                Partnership::whereIn('id', $partnershipIds)
                    ->where('status', PartnershipStatus::LIVE)
                    ->update([
                        'status'     => PartnershipStatus::SUSPENDED,
                        'updated_at' => now(),
                    ]);
            }
        });

        Log::info('Ecosystem webhook: merchant exit processed.', ['merchant_id' => $merchantId]);

        return response()->json(['message' => 'Merchant exit processed.', 'merchant_id' => $merchantId]);
    }

    /**
     * POST /api/webhooks/ecosystem/merchant-reactivate
     *
     * Body: { merchant_id: int }
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function merchantReactivate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
        ]);

        $merchantId = (int) $data['merchant_id'];

        DB::table('merchants')
            ->where('id', $merchantId)
            ->update([
                'ecosystem_active' => true,
                'updated_at'       => now(),
            ]);

        Log::info('Ecosystem webhook: merchant reactivated.', ['merchant_id' => $merchantId]);

        // Partnerships are NOT auto-resumed. Super admin reviews and re-activates manually.
        return response()->json([
            'message'    => 'Merchant reactivated. Partnerships must be manually resumed by super admin.',
            'merchant_id' => $merchantId,
        ]);
    }
}
