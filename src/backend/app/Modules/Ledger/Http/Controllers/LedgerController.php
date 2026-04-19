<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LedgerController — read-only statement endpoints.
 *
 * Owner module: Ledger
 * Reads: partner_ledger_entries (via StatementService)
 * Writes: nothing
 */
class LedgerController extends Controller
{
    public function __construct(private readonly StatementService $statements) {}

    /**
     * GET /api/partnerships/{uuid}/ledger
     * Monthly statement for one partnership from the caller's merchant perspective.
     */
    public function partnershipStatement(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $months = min((int) $request->query('months', 6), 24);
        $data   = $this->statements->forPartnership(
            $partnership->id,
            $request->user()->merchant_id,
            $months,
        );

        return response()->json([
            'partnership_id' => $uuid,
            'merchant_id'    => $request->user()->merchant_id,
            'months'         => $months,
            'statement'      => $data,
        ]);
    }

    /**
     * GET /api/ledger/summary
     * All-partnerships monthly summary for the authenticated merchant.
     */
    public function merchantSummary(Request $request): JsonResponse
    {
        $months = min((int) $request->query('months', 6), 24);
        $data   = $this->statements->summaryForMerchant(
            $request->user()->merchant_id,
            $months,
        );

        return response()->json([
            'merchant_id' => $request->user()->merchant_id,
            'months'      => $months,
            'summary'     => $data,
        ]);
    }
}
