<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsAppCredit\Services\WhatsAppCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super admin credit allocation — allocate WhatsApp credits to merchants.
 *
 * Owner module: SuperAdmin
 * Calls: WhatsAppCreditService
 */
class CreditAllocationController extends Controller
{
    public function __construct(
        private readonly WhatsAppCreditService $credits,
    ) {}

    /**
     * POST /api/super-admin/merchants/{merchantId}/allocate-credits
     */
    public function allocate(Request $request, int $merchantId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $newBalance = $this->credits->allocate(
            merchantId:  $merchantId,
            amount:      $data['amount'],
            allocatedBy: $request->user('sanctum')->id,
            note:        $data['note'] ?? '',
        );

        return response()->json([
            'merchant_id' => $merchantId,
            'allocated'   => $data['amount'],
            'new_balance' => $newBalance,
        ]);
    }
}
