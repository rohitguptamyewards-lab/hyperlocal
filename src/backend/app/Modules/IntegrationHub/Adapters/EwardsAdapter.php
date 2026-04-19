<?php

namespace App\Modules\IntegrationHub\Adapters;

use App\Modules\IntegrationHub\Contracts\LoyaltyAdapter;
use App\Modules\IntegrationHub\Contracts\PosAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eWards integration adapter.
 * Implements LoyaltyAdapter, PosAdapter.
 *
 * SKELETON — all methods throw until eWards API contracts arrive.
 * Replace the throw with real HTTP calls once the spec is received.
 *
 * Config keys expected in merchant_integrations.config:
 *   - api_key: eWards API key for this merchant
 *   - base_url: eWards API base URL
 *   - brand_id: eWards brand/merchant ID
 *
 * Owner module: IntegrationHub
 * DO NOT call directly — always go through IntegrationResolverService.
 */
class EwardsAdapter implements LoyaltyAdapter, PosAdapter
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $brandId,
    ) {}

    // ── LoyaltyAdapter ────────────────────────────────────────

    /**
     * @inheritDoc
     * @throws \RuntimeException — eWards spec pending
     */
    public function getBalance(string $phone, int $merchantId): float
    {
        // TODO: implement when eWards API spec arrives
        // GET {base_url}/members/{phone}/balance?brand_id={brandId}
        throw new \RuntimeException('EwardsAdapter::getBalance not yet implemented — awaiting spec.');
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException — eWards spec pending
     */
    public function award(string $phone, int $merchantId, float $amount, string $reference): bool
    {
        // TODO: implement when eWards API spec arrives
        // POST {base_url}/points/award
        throw new \RuntimeException('EwardsAdapter::award not yet implemented — awaiting spec.');
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException — eWards spec pending
     */
    public function deduct(string $phone, int $merchantId, float $amount, string $reference): bool
    {
        // TODO: implement when eWards API spec arrives
        // POST {base_url}/points/deduct
        throw new \RuntimeException('EwardsAdapter::deduct not yet implemented — awaiting spec.');
    }

    /**
     * @inheritDoc
     * Pulls paginated customer list from eWards API for member sync.
     * @throws \RuntimeException — eWards spec pending
     */
    public function getCustomers(int $merchantId, int $page, int $perPage): array
    {
        // TODO: implement when eWards API spec arrives
        // GET {base_url}/members?brand_id={brandId}&page={page}&per_page={perPage}
        // Expected response: { data: [{phone, name, external_id}], has_more: bool, total: int }
        throw new \RuntimeException('EwardsAdapter::getCustomers not yet implemented — awaiting spec.');
    }

    // ── PosAdapter ────────────────────────────────────────────

    /**
     * @inheritDoc
     * @throws \RuntimeException — eWards spec pending
     */
    public function pushRedemption(string $transactionId, array $payload): bool
    {
        // TODO: implement when eWards API spec arrives
        // POST {base_url}/redemptions
        throw new \RuntimeException('EwardsAdapter::pushRedemption not yet implemented — awaiting spec.');
    }
}
