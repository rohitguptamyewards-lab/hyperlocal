<?php

namespace App\Modules\Member\Jobs;

use App\Modules\IntegrationHub\Services\IntegrationResolverService;
use App\Modules\Member\Services\MemberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pulls customer phone numbers from an external integration (e.g. eWards)
 * and upserts them into our local `members` table.
 *
 * Dispatched by: EwardsRequestService::approve()
 * Can also be dispatched manually by super admin for a re-sync.
 *
 * Chunked: fetches CUSTOMERS_PER_PAGE at a time from the adapter,
 * processes each page before fetching the next — avoids loading all
 * customers into memory at once.
 *
 * Idempotent: findOrCreateByPhone + linkExternal both use upsert semantics.
 * Safe to re-run; will not create duplicate member rows.
 *
 * Queue: 'default' — non-critical background sync.
 *
 * Owner module: Member
 * Reads: merchant_integrations (via IntegrationResolverService)
 * Writes: members, member_integrations
 */
class SyncMembersFromIntegration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CUSTOMERS_PER_PAGE = 200;

    public int $tries   = 3;
    public int $timeout = 600; // 10 minutes — large merchants may have 50k+ members

    public function __construct(
        private readonly int    $merchantId,
        private readonly string $provider,   // e.g. 'ewrds'
    ) {}

    /**
     * @param  IntegrationResolverService $resolver
     * @param  MemberService              $memberService
     */
    public function handle(IntegrationResolverService $resolver, MemberService $memberService): void
    {
        // Resolve the adapter for this merchant + provider
        try {
            $adapter = $resolver->resolveForMerchant($this->merchantId, $this->provider);
        } catch (\Throwable $e) {
            Log::error('SyncMembersFromIntegration: could not resolve adapter.', [
                'merchant_id' => $this->merchantId,
                'provider'    => $this->provider,
                'error'       => $e->getMessage(),
            ]);
            $this->fail($e);
            return;
        }

        $page       = 1;
        $synced     = 0;
        $skipped    = 0;

        do {
            try {
                $result = $adapter->getCustomers($this->merchantId, $page, self::CUSTOMERS_PER_PAGE);
            } catch (\RuntimeException $e) {
                Log::error('SyncMembersFromIntegration: adapter getCustomers failed.', [
                    'merchant_id' => $this->merchantId,
                    'provider'    => $this->provider,
                    'page'        => $page,
                    'error'       => $e->getMessage(),
                ]);
                $this->fail($e);
                return;
            }

            foreach ($result['data'] as $customer) {
                $rawPhone = $customer['phone'] ?? null;

                if (!$rawPhone) {
                    $skipped++;
                    continue;
                }

                try {
                    $phone  = $memberService->normalise($rawPhone);
                    $member = $memberService->findOrCreateByPhone($phone, $customer['name'] ?? null);

                    // Link external identity if we have an external_id
                    if (!empty($customer['external_id'])) {
                        $memberService->linkExternal($member, $this->provider, $customer['external_id']);
                    }

                    $synced++;
                } catch (\Throwable $e) {
                    // Per-member failures are non-fatal — log and continue
                    $skipped++;
                    Log::warning('SyncMembersFromIntegration: member upsert failed.', [
                        'merchant_id' => $this->merchantId,
                        'phone'       => $rawPhone,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $hasMore = $result['has_more'] ?? false;
            $page++;
        } while ($hasMore);

        Log::info('SyncMembersFromIntegration: completed.', [
            'merchant_id' => $this->merchantId,
            'provider'    => $this->provider,
            'synced'      => $synced,
            'skipped'     => $skipped,
            'pages'       => $page - 1,
        ]);
    }
}
