<?php

namespace App\Modules\PartnerOffers\Services;

use App\Models\User;
use App\Modules\PartnerOffers\Constants\OfferDisplayTemplate;
use App\Modules\PartnerOffers\Constants\OfferStatus;
use App\Modules\PartnerOffers\Models\PartnerOffer;
use App\Modules\PartnerOffers\Models\PartnerOfferAttachment;
use App\Modules\PartnerOffers\Models\PartnerOfferNetworkPublication;
use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD, attach/detach, publish/unpublish for partner offers.
 *
 * Owner module: PartnerOffers
 * Tables owned: partner_offers, partner_offer_attachments, partner_offer_network_publications
 */
class PartnerOfferService
{
    public function listForMerchant(int $merchantId): \Illuminate\Database\Eloquent\Collection
    {
        return PartnerOffer::forMerchant($merchantId)
            ->withCount(['attachments as active_attachments_count' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['networkPublications as active_publications_count' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, array $data): PartnerOffer
    {
        $this->validateDisplayTemplate($data['display_template'] ?? 'simple');

        return PartnerOffer::create([
            'merchant_id'          => $user->merchant_id,
            'title'                => $data['title'],
            'description'          => $data['description'] ?? null,
            'coupon_code'          => $data['coupon_code'],
            'discount_type'        => $data['discount_type'] ?? 1,
            'discount_value'       => $data['discount_value'] ?? 0,
            'image_url'            => $data['image_url'] ?? null,
            'expiry_date'          => $data['expiry_date'] ?? null,
            'terms_conditions'     => $data['terms_conditions'] ?? null,
            'display_template'     => $data['display_template'] ?? 'simple',
            'status'               => OfferStatus::ACTIVE,
            // eWards-style fields
            'max_issuance'         => $data['max_issuance'] ?? null,
            'max_redemptions'      => $data['max_redemptions'] ?? null,
            'pos_redemption_type'  => $data['pos_redemption_type'] ?? 'flat',
            'flat_discount_amount' => $data['flat_discount_amount'] ?? null,
            'discount_percentage'  => $data['discount_percentage'] ?? null,
            'max_cap_amount'       => $data['max_cap_amount'] ?? null,
            'created_by'           => $user->id,
            'updated_by'           => $user->id,
        ]);
    }

    public function update(User $user, PartnerOffer $offer, array $data): PartnerOffer
    {
        $this->guardOwnership($offer, $user->merchant_id);

        if (isset($data['display_template'])) {
            $this->validateDisplayTemplate($data['display_template']);
        }

        $offer->update(array_merge(
            array_filter($data, fn ($v) => $v !== null),
            ['updated_by' => $user->id],
        ));

        return $offer->fresh();
    }

    public function toggleStatus(User $user, PartnerOffer $offer): PartnerOffer
    {
        $this->guardOwnership($offer, $user->merchant_id);

        $offer->update([
            'status'     => $offer->status === OfferStatus::ACTIVE ? OfferStatus::INACTIVE : OfferStatus::ACTIVE,
            'updated_by' => $user->id,
        ]);

        return $offer->fresh();
    }

    /**
     * Brand B attaches Brand A's offer to a partnership.
     */
    public function attachToPartnership(User $user, PartnerOffer $offer, int $partnershipId): PartnerOfferAttachment
    {
        $partnership = Partnership::findOrFail($partnershipId);

        if ($partnership->status !== PartnershipStatus::LIVE) {
            throw ValidationException::withMessages([
                'partnership_id' => ['Partnership must be LIVE to attach offers.'],
            ]);
        }

        // Verify the user's merchant is a participant
        $isParticipant = $partnership->participants()
            ->where('merchant_id', $user->merchant_id)
            ->exists();

        if (!$isParticipant) {
            throw ValidationException::withMessages([
                'partnership_id' => ['You are not a participant in this partnership.'],
            ]);
        }

        return PartnerOfferAttachment::updateOrCreate(
            ['offer_id' => $offer->id, 'partnership_id' => $partnershipId],
            [
                'attached_by_merchant_id' => $user->merchant_id,
                'is_active'              => true,
                'created_by'             => $user->id,
                'updated_by'             => $user->id,
            ],
        );
    }

    public function detachFromPartnership(int $offerId, int $partnershipId): void
    {
        PartnerOfferAttachment::where('offer_id', $offerId)
            ->where('partnership_id', $partnershipId)
            ->delete();
    }

    public function publishToNetwork(User $user, PartnerOffer $offer, int $networkId): PartnerOfferNetworkPublication
    {
        $this->guardOwnership($offer, $user->merchant_id);

        // Verify merchant is a member of the network
        $isMember = DB::table('network_memberships')
            ->where('network_id', $networkId)
            ->where('merchant_id', $user->merchant_id)
            ->where('status', 1)
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'network_id' => ['You are not a member of this network.'],
            ]);
        }

        return PartnerOfferNetworkPublication::updateOrCreate(
            ['offer_id' => $offer->id, 'network_id' => $networkId],
            [
                'is_active'  => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );
    }

    public function unpublishFromNetwork(int $offerId, int $networkId): void
    {
        PartnerOfferNetworkPublication::where('offer_id', $offerId)
            ->where('network_id', $networkId)
            ->delete();
    }

    /**
     * Get offers from a partner that are available for Brand B to attach.
     */
    public function availableForPartnership(int $partnershipId, int $myMerchantId): \Illuminate\Support\Collection
    {
        $partnership = Partnership::with('participants')->findOrFail($partnershipId);

        $partnerMerchantId = $partnership->participants
            ->where('merchant_id', '!=', $myMerchantId)
            ->first()
            ?->merchant_id;

        if (!$partnerMerchantId) {
            return collect();
        }

        return PartnerOffer::forMerchant($partnerMerchantId)
            ->active()
            ->notExpired()
            ->get()
            ->map(function ($offer) use ($partnershipId) {
                $attachment = $offer->attachments()
                    ->where('partnership_id', $partnershipId)
                    ->first();
                $offer->is_attached = (bool) $attachment;
                $offer->attachment_active = $attachment?->is_active ?? false;
                return $offer;
            });
    }

    private function guardOwnership(PartnerOffer $offer, int $merchantId): void
    {
        if ($offer->merchant_id !== $merchantId) {
            throw ValidationException::withMessages([
                'offer' => ['This offer does not belong to your brand.'],
            ]);
        }
    }

    private function validateDisplayTemplate(string $template): void
    {
        if (!in_array($template, OfferDisplayTemplate::VALID_KEYS, true)) {
            throw ValidationException::withMessages([
                'display_template' => ['Invalid display template. Must be: ' . implode(', ', OfferDisplayTemplate::VALID_KEYS)],
            ]);
        }
    }
}
