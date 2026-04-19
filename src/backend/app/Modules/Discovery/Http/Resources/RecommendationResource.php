<?php

namespace App\Modules\Discovery\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RecommendationResource — serialises a partner_recommendation row for the API.
 *
 * Owner module: Discovery
 *
 * Shape returned:
 * {
 *   id, fit_score, confidence_tier, rationale,
 *   merchant: { id, uuid, name, category, city, pincode, primary_outlet },
 *   computed_at, expires_at
 * }
 */
class RecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        $merchant = $this->recommendedMerchant;
        $outlets  = $merchant?->outlets ?? collect();

        // Pick the first active outlet as the "primary" one for display
        $primary = $outlets->firstWhere('is_active', true);

        return [
            'id'              => $this->id,
            'fit_score'       => round((float) $this->fit_score, 4),
            'confidence_tier' => $this->confidence_tier,
            'rationale'       => $this->rationale,
            'merchant'        => $merchant ? [
                'id'       => $merchant->id,
                'uuid'     => $merchant->uuid,
                'name'     => $merchant->name,
                'category' => $merchant->category,
                'city'     => $merchant->city,
                'pincode'  => $merchant->pincode,
                'primary_outlet' => $primary ? [
                    'id'      => $primary->id,
                    'name'    => $primary->name,
                    'address' => $primary->address,
                    'city'    => $primary->city,
                ] : null,
            ] : null,
            'computed_at' => $this->computed_at?->toISOString(),
            'expires_at'  => $this->expires_at?->toISOString(),
        ];
    }
}
