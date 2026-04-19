<?php

namespace App\Modules\IntegrationHub\Services;

use App\Modules\IntegrationHub\Adapters\EwardsAdapter;
use App\Modules\IntegrationHub\Adapters\LocalLoyaltyAdapter;
use App\Modules\IntegrationHub\Contracts\LoyaltyAdapter;
use App\Modules\IntegrationHub\Contracts\PosAdapter;
use App\Modules\IntegrationHub\Models\MerchantIntegration;

/**
 * Resolves the correct adapter instance for a merchant.
 *
 * Usage:
 *   $loyalty = $resolver->loyalty($merchantId);  // always returns something
 *   $pos     = $resolver->pos($merchantId);      // null if not configured
 *
 * Owner module: IntegrationHub
 * Reads: merchant_integrations
 * DO NOT instantiate adapters directly — always use this service.
 */
class IntegrationResolverService
{
    /**
     * Returns the loyalty adapter for a merchant.
     * Falls back to LocalLoyaltyAdapter if no external provider is configured.
     *
     * @param  int $merchantId
     * @return LoyaltyAdapter
     */
    public function loyalty(int $merchantId): LoyaltyAdapter
    {
        $integration = MerchantIntegration::where('merchant_id', $merchantId)
            ->where('is_loyalty_source', true)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return new LocalLoyaltyAdapter();
        }

        return $this->buildAdapter($integration->provider, $integration->config ?? []);
    }

    /**
     * Returns the POS adapter for a merchant, or null if none configured.
     *
     * @param  int $merchantId
     * @return PosAdapter|null
     */
    public function pos(int $merchantId): ?PosAdapter
    {
        $integration = MerchantIntegration::where('merchant_id', $merchantId)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return null;
        }

        $adapter = $this->buildAdapter($integration->provider, $integration->config ?? []);

        return $adapter instanceof PosAdapter ? $adapter : null;
    }

    /**
     * Resolve a specific provider adapter for a merchant.
     * Used by sync jobs that know the provider name up-front.
     *
     * @param  int    $merchantId
     * @param  string $provider    e.g. 'ewrds'
     * @return LoyaltyAdapter
     * @throws \RuntimeException If integration is not active or provider unknown
     */
    public function resolveForMerchant(int $merchantId, string $provider): LoyaltyAdapter
    {
        $integration = MerchantIntegration::where('merchant_id', $merchantId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            throw new \RuntimeException(
                "No active {$provider} integration for merchant {$merchantId}."
            );
        }

        $adapter = $this->buildAdapter($integration->provider, $integration->config ?? []);

        if (!$adapter instanceof LoyaltyAdapter) {
            throw new \RuntimeException("Provider {$provider} does not implement LoyaltyAdapter.");
        }

        return $adapter;
    }

    // ─────────────────────────────────────────────────────────

    /**
     * @param  string $provider
     * @param  array  $config   Decrypted config from merchant_integrations
     * @return object           An adapter instance (may implement multiple contracts)
     * @throws \RuntimeException For unknown providers
     */
    private function buildAdapter(string $provider, array $config): object
    {
        return match ($provider) {
            'ewrds' => new EwardsAdapter(
                apiKey:  $config['api_key']  ?? '',
                baseUrl: $config['base_url'] ?? '',
                brandId: $config['brand_id'] ?? '',
            ),
            'local' => new LocalLoyaltyAdapter(),
            default => throw new \RuntimeException("Unknown integration provider: {$provider}"),
        };
    }
}
