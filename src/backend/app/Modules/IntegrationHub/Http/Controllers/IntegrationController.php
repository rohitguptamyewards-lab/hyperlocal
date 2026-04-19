<?php

namespace App\Modules\IntegrationHub\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IntegrationHub\Models\MerchantIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Manage integrations for the authenticated merchant.
 * Config is stored encrypted — never returned to client in plaintext.
 *
 * Owner module: IntegrationHub
 * Integration points: MerchantIntegration model (encrypted config)
 */
class IntegrationController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['ewrds', 'capillary', 'pos_xyz', 'generic_pos'];

    /**
     * List all integrations for the authenticated merchant (config redacted).
     *
     * GET /merchant/integrations
     */
    public function index(Request $request): JsonResponse
    {
        $integrations = MerchantIntegration::where('merchant_id', $request->user()->merchant_id)
            ->get()
            ->map(fn ($i) => [
                'id'                => $i->id,
                'provider'          => $i->provider,
                'is_loyalty_source' => $i->is_loyalty_source,
                'is_active'         => $i->is_active,
                'has_config'        => !empty($i->config),
                'updated_at'        => $i->updated_at?->toIso8601String(),
            ]);

        return response()->json($integrations);
    }

    /**
     * Add or update an integration for the authenticated merchant.
     * If is_loyalty_source = true, clears loyalty_source on all other integrations first.
     *
     * POST /merchant/integrations
     */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'          => ['required', 'string', 'in:' . implode(',', self::SUPPORTED_PROVIDERS)],
            'config'            => ['required', 'array'],
            'is_loyalty_source' => ['boolean'],
        ]);

        $merchantId = $request->user()->merchant_id;

        if (!empty($data['is_loyalty_source'])) {
            // Clear loyalty source flag from all other integrations for this merchant
            MerchantIntegration::where('merchant_id', $merchantId)
                ->where('provider', '!=', $data['provider'])
                ->update(['is_loyalty_source' => false]);
        }

        $integration = MerchantIntegration::updateOrCreate(
            ['merchant_id' => $merchantId, 'provider' => $data['provider']],
            [
                'config'            => $data['config'],
                'is_loyalty_source' => $data['is_loyalty_source'] ?? false,
                'is_active'         => true,
            ],
        );

        return response()->json([
            'id'                => $integration->id,
            'provider'          => $integration->provider,
            'is_loyalty_source' => $integration->is_loyalty_source,
            'is_active'         => $integration->is_active,
        ], 201);
    }

    /**
     * Deactivate an integration (soft — keeps config for re-activation).
     *
     * DELETE /merchant/integrations/{provider}
     */
    public function deactivate(Request $request, string $provider): JsonResponse
    {
        $integration = MerchantIntegration::where('merchant_id', $request->user()->merchant_id)
            ->where('provider', $provider)
            ->firstOrFail();

        $integration->update(['is_active' => false]);

        return response()->json(['message' => "Integration '{$provider}' deactivated."]);
    }
}
