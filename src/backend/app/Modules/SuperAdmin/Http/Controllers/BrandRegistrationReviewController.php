<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin endpoints for reviewing self-registered brand registrations.
 *
 * GET  /api/super-admin/brand-registrations           — list (filterable by status)
 * POST /api/super-admin/brand-registrations/{id}/approve — approve brand
 * POST /api/super-admin/brand-registrations/{id}/reject  — reject with reason
 *
 * Owner module: SuperAdmin
 */
class BrandRegistrationReviewController extends Controller
{
    /**
     * GET /api/super-admin/brand-registrations
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Merchant::query()
            ->select('id', 'name', 'email', 'phone', 'category', 'city', 'state',
                     'registration_status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'created_at')
            ->whereNotNull('registration_status')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('registration_status', $status);
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /api/super-admin/brand-registrations/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);

        $merchant->update([
            'registration_status' => 'approved',
            'is_active'           => true,
            'ecosystem_active'    => true,
            'reviewed_by'         => $request->user()->id,
            'reviewed_at'         => now(),
            'rejection_reason'    => null,
        ]);

        return response()->json([
            'message'     => 'Brand approved and activated.',
            'merchant_id' => $merchant->id,
        ]);
    }

    /**
     * POST /api/super-admin/brand-registrations/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $merchant = Merchant::findOrFail($id);

        $merchant->update([
            'registration_status' => 'rejected',
            'is_active'           => false,
            'reviewed_by'         => $request->user()->id,
            'reviewed_at'         => now(),
            'rejection_reason'    => $data['reason'],
        ]);

        return response()->json([
            'message'     => 'Brand registration rejected.',
            'merchant_id' => $merchant->id,
        ]);
    }
}
