<?php

namespace App\Modules\Execution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cashier-facing redemption response.
 * Designed to be unambiguous on a small screen in a busy outlet.
 */
class RedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'redemption_id'        => $this['redemption_id'],
            'allowed'              => true,
            'benefit_amount'       => $this['benefit_amount'],
            'customer_type'        => $this['customer_type'],
            'customer_type_label'  => $this['customer_type_label'],
            'duplicate'            => $this['duplicate'] ?? false,
        ];
    }
}
