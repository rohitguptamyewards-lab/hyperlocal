<?php

namespace App\Modules\EwardsRequest\Services;

use App\Modules\EwardsRequest\Models\EwardsIntegrationRequest;
use App\Modules\Member\Jobs\SyncMembersFromIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * eWards integration request lifecycle: submit, approve, reject.
 *
 * On approval: upserts merchant_integrations row with eWards credentials
 * and dispatches SyncMembersFromIntegration job to pull customer phone numbers.
 *
 * Owner module: EwardsRequest
 * Tables written: ewards_integration_requests, merchant_integrations
 * Dispatches: SyncMembersFromIntegration
 */
class EwardsRequestService
{
    /**
     * Merchant submits a new eWards integration request.
     *
     * Rules:
     *  - If already approved (active integration) → reject with message
     *  - If pending → reject with "already pending" message
     *  - If previously rejected → soft-delete old row, allow new request
     *
     * @throws ValidationException
     */
    public function submit(int $merchantId, int $requestedBy, ?string $notes): EwardsIntegrationRequest
    {
        // Check for active eWards integration
        $activeIntegration = DB::table('merchant_integrations')
            ->where('merchant_id', $merchantId)
            ->where('provider', 'ewrds')
            ->where('is_active', true)
            ->exists();

        if ($activeIntegration) {
            throw ValidationException::withMessages([
                'merchant_id' => ['eWards integration is already active for your merchant.'],
            ]);
        }

        // Check for existing pending request
        $existing = EwardsIntegrationRequest::where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing?->isPending()) {
            throw ValidationException::withMessages([
                'merchant_id' => ['You already have a pending eWards request.'],
            ]);
        }

        // Soft-delete any old rejected row so we can create a fresh one
        if ($existing?->isRejected()) {
            $existing->delete();
        }

        return EwardsIntegrationRequest::create([
            'merchant_id'  => $merchantId,
            'status'       => 'pending',
            'requested_by' => $requestedBy,
            'notes'        => $notes,
        ]);
    }

    /**
     * Super admin approves the request.
     * Creates/activates the merchant_integrations row with provided credentials.
     * Dispatches member sync job.
     *
     * @param  array{api_key: string, base_url: string, brand_id: string} $config
     * @throws ValidationException
     */
    public function approve(int $requestId, int $reviewedBy, array $config): EwardsIntegrationRequest
    {
        $request = EwardsIntegrationRequest::findOrFail($requestId);

        if (!$request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending requests can be approved.'],
            ]);
        }

        DB::transaction(function () use ($request, $reviewedBy, $config): void {
            // Upsert merchant_integrations with encrypted config
            DB::table('merchant_integrations')->updateOrInsert(
                ['merchant_id' => $request->merchant_id, 'provider' => 'ewrds'],
                [
                    'config'           => encrypt($config),
                    'is_active'        => true,
                    'is_loyalty_source'=> false, // merchant opts in separately
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );

            $request->update([
                'status'      => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ]);
        });

        // Dispatch member sync outside transaction — best-effort
        SyncMembersFromIntegration::dispatch($request->merchant_id, 'ewrds')
            ->onQueue('default');

        return $request->fresh();
    }

    /**
     * Super admin rejects the request.
     *
     * @throws ValidationException
     */
    public function reject(int $requestId, int $reviewedBy, string $reason): EwardsIntegrationRequest
    {
        $request = EwardsIntegrationRequest::findOrFail($requestId);

        if (!$request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending requests can be rejected.'],
            ]);
        }

        $request->update([
            'status'           => 'rejected',
            'reviewed_by'      => $reviewedBy,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh();
    }

    /**
     * Get the current request for a merchant (null if none).
     */
    public function getForMerchant(int $merchantId): ?EwardsIntegrationRequest
    {
        return EwardsIntegrationRequest::where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->latest()
            ->first();
    }
}
