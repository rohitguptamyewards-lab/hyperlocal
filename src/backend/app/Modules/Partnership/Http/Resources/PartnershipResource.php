<?php

namespace App\Modules\Partnership\Http\Resources;

use App\Modules\Partnership\Constants\PartnershipTC;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requestingMerchantId = $request->user()?->merchant_id;
        $partnerParticipant = $this->relationLoaded('participants')
            ? $this->participants->first(
                fn ($participant) => (int) $participant->merchant_id !== (int) $requestingMerchantId
            )
            : null;

        return [
            'id'              => $this->uuid,
            'uuid'            => $this->uuid,
            'name'            => $this->name,
            'partner_name'    => $partnerParticipant?->merchant?->name ?? $this->name,
            'scope_type'      => $this->scope_type,
            'offer_structure' => $this->offer_structure ?? 'same',
            'status'          => $this->status,
            'status_label'  => $this->statusLabel(),
            'is_brand_level'=> $this->isBrandLevel(),
            'start_at'      => $this->start_at?->toIso8601String(),
            'end_at'        => $this->end_at?->toIso8601String(),
            'paused_at'     => $this->paused_at?->toIso8601String(),
            'paused_reason' => $this->paused_reason,
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),

            'participants'  => $this->whenLoaded('participants', fn () =>
                $this->participants->map(fn ($p) => [
                    'merchant_id'     => $p->merchant_id,
                    'merchant_name'   => $p->merchant?->name,
                    'outlet_id'       => $p->outlet_id,
                    'outlet_name'     => $p->outlet?->name,
                    'role'              => $p->role,
                    'approval_status'   => $p->approval_status,
                    'is_brand_wide'     => $p->isBrandWide(),
                    'is_suspended'       => $p->isSuspended(),
                    'suspended_at'       => $p->suspended_at?->toIso8601String(),
                    'suspension_reason'  => $p->suspension_reason,
                    'issuing_enabled'    => (bool) ($p->issuing_enabled ?? true),
                    'redemption_enabled' => (bool) ($p->redemption_enabled ?? true),
                    'campaigns_enabled'  => (bool) ($p->campaigns_enabled ?? true),
                    'bill_offers_enabled'=> (bool) ($p->bill_offers_enabled ?? true),
                    // Per-participant offer (used when offer_structure = 'different')
                    'offer_pos_type'    => $p->offer_pos_type,
                    'offer_flat_amount' => $p->offer_flat_amount !== null ? (float) $p->offer_flat_amount : null,
                    'offer_percentage'  => $p->offer_percentage  !== null ? (float) $p->offer_percentage  : null,
                    'offer_max_cap'     => $p->offer_max_cap     !== null ? (float) $p->offer_max_cap     : null,
                    'offer_min_bill'    => $p->offer_min_bill    !== null ? (float) $p->offer_min_bill    : null,
                    'offer_monthly_cap' => $p->offer_monthly_cap !== null ? (float) $p->offer_monthly_cap : null,
                    'offer_filled'      => (bool) ($p->offer_filled ?? false),
                ])
            ),

            'terms' => $this->whenLoaded('terms', fn () => $this->terms ? [
                'per_bill_cap_amount'           => $this->terms->per_bill_cap_amount,
                'per_bill_cap_percent'          => $this->terms->per_bill_cap_percent,
                'per_bill_cap_points'           => $this->terms->per_bill_cap_points,
                'min_bill_amount'               => $this->terms->min_bill_amount,
                'min_bill_points'               => $this->terms->min_bill_points,
                'monthly_cap_amount'            => $this->terms->monthly_cap_amount,
                'monthly_cap_points'            => $this->terms->monthly_cap_points,
                'rupees_per_point_at_agreement' => $this->terms->rupees_per_point_at_agreement,
                'approval_mode'                 => $this->terms->approval_mode,
                'version'                       => $this->terms->version,
                'daily_cap_amount'              => $this->terms->daily_cap_amount,
                'daily_cap_points'              => $this->terms->daily_cap_points,
                'daily_transaction_count'       => $this->terms->daily_transaction_count,
                'outlet_daily_cap_amount'       => $this->terms->outlet_daily_cap_amount,
                'outlet_daily_count'            => $this->terms->outlet_daily_count,
                'outlet_per_bill_cap_amount'    => $this->terms->outlet_per_bill_cap_amount,
                'lifetime_cap_amount'           => $this->terms->lifetime_cap_amount,
                'lifetime_cap_points'           => $this->terms->lifetime_cap_points,
                'notify_on_limit_hit'           => (bool) ($this->terms->notify_on_limit_hit ?? false),
                'notify_partner_on_limit_hit'   => (bool) ($this->terms->notify_partner_on_limit_hit ?? false),
                'pause_on_monthly_limit'        => (bool) ($this->terms->pause_on_monthly_limit ?? false),
            ] : null),

            'rules' => $this->whenLoaded('rules', fn () => $this->rules ? [
                'customer_type_rules' => $this->rules->customer_type_rules,
                'inactivity_days'     => $this->rules->inactivity_days,
                'blackout_rules'      => $this->rules->blackout_rules,
                'time_band_rules'     => $this->rules->time_band_rules,
                'first_time_only'     => $this->rules->first_time_only,
                'uses_per_customer'   => $this->rules->uses_per_customer,
                'cooling_period_days' => $this->rules->cooling_period_days,
                'version'             => $this->rules->version,
            ] : null),

            'agreements' => $this->whenLoaded('agreements', fn () =>
                $this->agreements->map(fn ($a) => [
                    'merchant_id' => $a->merchant_id,
                    'version'     => $a->version,
                    'accepted_at' => $a->accepted_at?->toIso8601String(),
                    'accepted_by' => $a->accepted_by,
                ])
            ),
            'tc_version' => PartnershipTC::VERSION,
        ];
    }
}
