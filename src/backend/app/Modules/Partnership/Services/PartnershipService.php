<?php

namespace App\Modules\Partnership\Services;

use App\Events\PartnershipLive;
use App\Models\User;
use App\Modules\Partnership\Constants\ParticipantRole;
use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Constants\ScopeType;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Models\PartnershipParticipant;
use App\Modules\Partnership\Models\PartnershipRules;
use App\Modules\MerchantSettings\Models\MerchantPointValuation;
use App\Modules\Partnership\Models\PartnershipTerms;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnershipService
{
    /**
     * Create a new partnership proposal.
     * Automatically creates both participant rows and optional terms.
     *
     * @throws ValidationException
     */
    public function create(User $user, array $data): Partnership
    {
        // Guard: prevent duplicate active partnerships between the same two merchants
        $this->checkDuplicatePartnership($user->merchant_id, $data['partner_merchant_id']);

        return DB::transaction(function () use ($user, $data): Partnership {
            $partnership = Partnership::create([
                'uuid'            => null, // set by model boot
                'merchant_id'     => $user->merchant_id,
                'name'            => $data['name'],
                'scope_type'      => $data['scope_type'],
                'offer_structure' => $data['offer_structure'] ?? 'same',
                'status'          => PartnershipStatus::REQUESTED,
                'start_at'        => $data['start_at'] ?? null,
                'end_at'          => $data['end_at'] ?? null,
                'created_by'      => $user->id,
                'updated_by'      => $user->id,
            ]);

            // Create proposer participants (with their offer config if different structure)
            $this->createParticipants(
                $partnership,
                $user->merchant_id,
                ParticipantRole::PROPOSER,
                ParticipantRole::APPROVAL_APPROVED, // proposer auto-approved
                $data['proposer_outlet_ids'] ?? null,
                $user->id,
                $data['proposer_offer'] ?? null,
            );

            // Create acceptor participants
            $this->createParticipants(
                $partnership,
                $data['partner_merchant_id'],
                ParticipantRole::ACCEPTOR,
                ParticipantRole::APPROVAL_PENDING,
                $data['acceptor_outlet_ids'] ?? null,
                $user->id,
                null, // acceptor fills their offer after accepting
            );

            // Create terms if provided
            if (!empty($data['terms'])) {
                $this->upsertTerms($partnership, $data['terms'], $user->id);
            }

            return $partnership->fresh($this->detailRelations());
        });
    }

    /**
     * Update name, dates, terms, and rules — only while partnership is editable.
     *
     * @throws ValidationException
     */
    public function update(User $user, Partnership $partnership, array $data): Partnership
    {
        return DB::transaction(function () use ($user, $partnership, $data): Partnership {
            $fields = array_filter([
                'name'       => $data['name'] ?? null,
                'start_at'   => $data['start_at'] ?? null,
                'end_at'     => $data['end_at'] ?? null,
                'updated_by' => $user->id,
            ], fn ($v) => !is_null($v));

            if (!empty($fields)) {
                $partnership->update($fields);
            }

            $termsOrRulesChanged = false;

            if (!empty($data['terms'])) {
                $this->upsertTerms($partnership, $data['terms'], $user->id);
                $termsOrRulesChanged = true;
            }

            if (!empty($data['rules'])) {
                $this->upsertRules($partnership, $data['rules'], $user->id);
                $termsOrRulesChanged = true;
            }

            // If terms/rules were changed while the partnership is still in REQUESTED
            // (or already NEGOTIATING), move it to NEGOTIATING so the other party
            // knows a counter-offer has been proposed.
            if ($termsOrRulesChanged
                && in_array($partnership->status, [PartnershipStatus::REQUESTED, PartnershipStatus::NEGOTIATING], true)
            ) {
                $partnership->update([
                    'status'     => PartnershipStatus::NEGOTIATING,
                    'updated_by' => $user->id,
                ]);
            }

            return $partnership->fresh($this->detailRelations());
        });
    }

    /**
     * Transition partnership status with guard.
     *
     * @throws ValidationException
     */
    public function transition(User $user, Partnership $partnership, int $newStatus, array $extra = []): Partnership
    {
        if (!$partnership->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$partnership->statusLabel()} to " . PartnershipStatus::label($newStatus),
            ]);
        }

        $update = array_merge(['status' => $newStatus, 'updated_by' => $user->id], $extra);
        $partnership->update($update);

        if ($newStatus === PartnershipStatus::LIVE) {
            PartnershipLive::dispatch($partnership->id, $user->id);
        }

        return $partnership->fresh($this->detailRelations());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Prevents two merchants from having more than one active partnership.
     * Active = any status except REJECTED, EXPIRED, or ECOSYSTEM_INACTIVE.
     *
     * @throws ValidationException
     */
    private function checkDuplicatePartnership(int $merchantA, int $merchantB): void
    {
        $terminalStatuses = [
            PartnershipStatus::REJECTED,
            PartnershipStatus::EXPIRED,
            PartnershipStatus::ECOSYSTEM_INACTIVE,
        ];

        $exists = Partnership::whereNotIn('status', $terminalStatuses)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($merchantA, $merchantB) {
                $q->whereHas('participants', fn ($p) => $p->where('merchant_id', $merchantA))
                  ->whereHas('participants', fn ($p) => $p->where('merchant_id', $merchantB));
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'partner_merchant_id' => ['An active partnership already exists with this brand.'],
            ]);
        }
    }

    /**
     * Save offer config for a participant (used when offer_structure = 'different').
     */
    public function saveParticipantOffer(int $partnershipId, int $merchantId, array $offer): void
    {
        PartnershipParticipant::where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->update([
                'offer_pos_type'    => $offer['pos_type'] ?? null,
                'offer_flat_amount' => $offer['flat_amount'] ?? null,
                'offer_percentage'  => $offer['percentage'] ?? null,
                'offer_max_cap'     => $offer['max_cap'] ?? null,
                'offer_min_bill'    => $offer['min_bill'] ?? null,
                'offer_monthly_cap' => $offer['monthly_cap'] ?? null,
                'linked_offer_id'   => $offer['linked_offer_id'] ?? null,
                'offer_filled'      => true,
            ]);
    }

    /**
     * @param array<int>|null $outletIds — null = brand-wide (outlet_id = NULL)
     */
    private function createParticipants(
        Partnership $partnership,
        int $merchantId,
        int $role,
        int $approvalStatus,
        ?array $outletIds,
        int $userId,
        ?array $offerConfig = null
    ): void {
        $offerFields = $offerConfig ? [
            'offer_pos_type'    => $offerConfig['pos_type'] ?? null,
            'offer_flat_amount' => $offerConfig['flat_amount'] ?? null,
            'offer_percentage'  => $offerConfig['percentage'] ?? null,
            'offer_max_cap'     => $offerConfig['max_cap'] ?? null,
            'offer_min_bill'    => $offerConfig['min_bill'] ?? null,
            'offer_monthly_cap' => $offerConfig['monthly_cap'] ?? null,
            'linked_offer_id'   => $offerConfig['linked_offer_id'] ?? null,
            'offer_filled'      => true,
        ] : [];

        $baseData = array_merge([
            'partnership_id'  => $partnership->id,
            'merchant_id'     => $merchantId,
            'role'            => $role,
            'approval_status' => $approvalStatus,
            'approved_by'     => $approvalStatus === ParticipantRole::APPROVAL_APPROVED ? $userId : null,
            'approved_at'     => $approvalStatus === ParticipantRole::APPROVAL_APPROVED ? now() : null,
            'created_by'      => $userId,
            'updated_by'      => $userId,
        ], $offerFields);

        if (empty($outletIds) || $partnership->scope_type === ScopeType::BRAND) {
            // Brand-wide: one row with outlet_id = NULL
            PartnershipParticipant::create(array_merge($baseData, ['outlet_id' => null]));
        } else {
            // Outlet-level: one row per outlet
            foreach ($outletIds as $outletId) {
                PartnershipParticipant::create(array_merge($baseData, ['outlet_id' => $outletId]));
            }
        }
    }

    /**
     * Resolve terms payload — converts any point-denominated limits to ₹
     * using the proposer merchant's current point valuation.
     *
     * Input keys accepted (all optional, any combination):
     *   per_bill_cap_percent    — % of bill (e.g. 20 for 20%)
     *   per_bill_cap_amount     — ₹ per bill cap
     *   per_bill_cap_points     — points per bill cap (converted to ₹)
     *   monthly_cap_amount      — ₹ monthly cap
     *   monthly_cap_points      — points monthly cap (converted to ₹)
     *   min_bill_amount         — ₹ min bill
     *   min_bill_points         — points min bill (converted to ₹)
     *   approval_mode           — 1=auto 2=manual
     *
     * Output: validated payload ready for DB upsert, with ₹ values resolved
     * and rupees_per_point_at_agreement locked if any points were provided.
     */
    private function upsertTerms(Partnership $partnership, array $terms, int $userId): void
    {
        $existing = $partnership->terms;

        // ── Resolve point-denominated fields ─────────────────
        $hasPoints = !empty($terms['per_bill_cap_points'])
                  || !empty($terms['monthly_cap_points'])
                  || !empty($terms['min_bill_points']);

        $lockedRate = null;

        if ($hasPoints) {
            // Use the previously locked rate if re-editing, otherwise fetch current
            $lockedRate = $existing?->rupees_per_point_at_agreement
                ?? (float) (MerchantPointValuation::current($partnership->merchant_id)?->rupees_per_point ?? 1.0);

            if (!empty($terms['per_bill_cap_points'])) {
                $terms['per_bill_cap_amount'] = round((float) $terms['per_bill_cap_points'] * $lockedRate, 2);
            }
            if (!empty($terms['monthly_cap_points'])) {
                $terms['monthly_cap_amount'] = round((float) $terms['monthly_cap_points'] * $lockedRate, 2);
            }
            if (!empty($terms['min_bill_points'])) {
                $terms['min_bill_amount'] = round((float) $terms['min_bill_points'] * $lockedRate, 2);
            }
        }

        $payload = array_merge($terms, [
            'partnership_id'              => $partnership->id,
            'merchant_id'                 => $partnership->merchant_id,
            'updated_by'                  => $userId,
            'rupees_per_point_at_agreement' => $hasPoints ? $lockedRate : ($existing?->rupees_per_point_at_agreement),
        ]);

        if ($existing) {
            $payload['version'] = $existing->version + 1;
            $existing->update($payload);
        } else {
            $payload['created_by'] = $userId;
            $payload['version']    = 1;
            PartnershipTerms::create($payload);
        }
    }

    private function upsertRules(Partnership $partnership, array $rules, int $userId): void
    {
        $existing = $partnership->rules;

        $payload = array_merge($rules, [
            'partnership_id' => $partnership->id,
            'merchant_id'    => $partnership->merchant_id,
            'updated_by'     => $userId,
        ]);

        if ($existing) {
            $payload['version'] = $existing->version + 1;
            $existing->update($payload);
        } else {
            $payload['created_by'] = $userId;
            $payload['version']    = 1;
            PartnershipRules::create($payload);
        }
    }

    /**
     * Consistent relationship graph for any partnership payload returned to the frontend.
     *
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return ['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'];
    }
}
