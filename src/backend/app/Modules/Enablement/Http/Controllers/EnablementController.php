<?php

namespace App\Modules\Enablement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Enablement\Models\StaffEnablement;
use App\Modules\Enablement\Services\DormancyService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EnablementController — staff enablement status for LIVE partnerships.
 *
 * Owner module: Enablement
 * Reads:  partnership_staff_enablement (via model)
 * Writes: last_training_at (POST /training)
 *
 * Endpoints:
 *   GET  /api/partnerships/{uuid}/enablement          → outlet enablement rows for this merchant
 *   POST /api/partnerships/{uuid}/enablement/{outletId}/training → record training
 */
class EnablementController extends Controller
{
    public function __construct(private readonly DormancyService $dormancy) {}

    /**
     * GET /api/partnerships/{uuid}/enablement
     * Returns enablement rows for the authenticated merchant's outlets in this partnership.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $rows = StaffEnablement::forMerchant($request->user()->merchant_id)
            ->forPartnership($partnership->id)
            ->with('outlet:id,name,address')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'outlet_id'           => $row->outlet_id,
                'outlet_name'         => $row->outlet?->name,
                'outlet_address'      => $row->outlet?->address,
                'last_training_at'    => $row->last_training_at?->toISOString(),
                'last_used_at'        => $row->last_used_at?->toISOString(),
                'is_dormant'          => $row->is_dormant,
                'dormant_since'       => $row->dormant_since?->toISOString(),
                'dormancy_alert_sent' => $row->dormancy_alert_sent,
                'days_since_activity' => $this->daysSinceActivity($row),
            ]);

        return response()->json($rows);
    }

    /**
     * POST /api/partnerships/{uuid}/enablement/{outletId}/training
     * Records a staff training session for the outlet.
     */
    public function markTraining(Request $request, string $uuid, int $outletId): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $row = StaffEnablement::forMerchant($request->user()->merchant_id)
            ->forPartnership($partnership->id)
            ->where('outlet_id', $outletId)
            ->firstOrFail();

        $row->update([
            'last_training_at' => now(),
            'updated_by'       => $request->user()->id,
        ]);

        return response()->json([
            'outlet_id'        => $outletId,
            'last_training_at' => $row->fresh()->last_training_at->toISOString(),
        ]);
    }

    /**
     * GET /api/enablement/summary
     * Dormancy summary for the authenticated merchant across all LIVE partnerships.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            $this->dormancy->summaryForMerchant($request->user()->merchant_id),
        );
    }

    private function daysSinceActivity(StaffEnablement $row): ?int
    {
        $lastActivity = $row->last_used_at ?? $row->created_at;
        return (int) $lastActivity->diffInDays(now());
    }
}
