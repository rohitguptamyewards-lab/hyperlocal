<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EwardsRequest\Services\EwardsRequestService;
use App\Modules\EwardsRequest\Models\EwardsIntegrationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin endpoints for managing eWards integration requests.
 *
 * GET  /api/super-admin/integration-requests          — list all (filterable by status)
 * GET  /api/super-admin/integration-requests/{id}     — single request detail
 * POST /api/super-admin/integration-requests/{id}/approve — approve with credentials
 * POST /api/super-admin/integration-requests/{id}/reject  — reject with reason
 *
 * Owner module: SuperAdmin
 * Delegates to: EwardsRequestService
 */
class IntegrationRequestController extends Controller
{
    public function __construct(
        private readonly EwardsRequestService $service,
    ) {}

    /**
     * GET /api/super-admin/integration-requests
     * List all requests, optionally filtered by status.
     *
     * @param  Request $request  (query: status=pending|approved|rejected, per_page=15)
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = EwardsIntegrationRequest::withTrashed()
            ->with(['merchant:id,name,email', 'requestedBy:id,name,email', 'reviewedBy:id,name,email'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if (!$request->boolean('include_deleted', false)) {
            $query->whereNull('deleted_at');
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($r) => $this->formatRequest($r)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/super-admin/integration-requests/{id}
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $req = EwardsIntegrationRequest::withTrashed()
            ->with(['merchant:id,name,email', 'requestedBy:id,name,email', 'reviewedBy:id,name,email'])
            ->findOrFail($id);

        return response()->json(['request' => $this->formatRequest($req)]);
    }

    /**
     * POST /api/super-admin/integration-requests/{id}/approve
     *
     * Body: { api_key: string, base_url: string, brand_id: string }
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'api_key'  => ['required', 'string', 'max:500'],
            'base_url' => ['required', 'url', 'max:500'],
            'brand_id' => ['required', 'string', 'max:100'],
        ]);

        $req = $this->service->approve(
            requestId:  $id,
            reviewedBy: $request->user()->id,
            config:     $data,
        );

        return response()->json([
            'message'     => 'Integration request approved. eWards is now active for this merchant.',
            'request_id'  => $req->id,
            'merchant_id' => $req->merchant_id,
            'status'      => $req->status,
            'reviewed_at' => $req->reviewed_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/super-admin/integration-requests/{id}/reject
     *
     * Body: { reason: string }
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $req = $this->service->reject(
            requestId:  $id,
            reviewedBy: $request->user()->id,
            reason:     $data['reason'],
        );

        return response()->json([
            'message'     => 'Integration request rejected.',
            'request_id'  => $req->id,
            'merchant_id' => $req->merchant_id,
            'status'      => $req->status,
            'reviewed_at' => $req->reviewed_at?->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatRequest(EwardsIntegrationRequest $r): array
    {
        return [
            'id'               => $r->id,
            'uuid'             => $r->uuid,
            'merchant_id'      => $r->merchant_id,
            'merchant_name'    => $r->merchant?->name,
            'merchant_email'   => $r->merchant?->email,
            'status'           => $r->status,
            'notes'            => $r->notes,
            'rejection_reason' => $r->rejection_reason,
            'requested_by'     => $r->requestedBy ? [
                'id'    => $r->requestedBy->id,
                'name'  => $r->requestedBy->name,
                'email' => $r->requestedBy->email,
            ] : null,
            'reviewed_by'      => $r->reviewedBy ? [
                'id'    => $r->reviewedBy->id,
                'name'  => $r->reviewedBy->name,
                'email' => $r->reviewedBy->email,
            ] : null,
            'reviewed_at'      => $r->reviewed_at?->toIso8601String(),
            'created_at'       => $r->created_at->toIso8601String(),
            'deleted_at'       => $r->deleted_at?->toIso8601String(),
        ];
    }
}
