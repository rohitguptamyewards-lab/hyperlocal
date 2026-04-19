<?php

namespace App\Modules\PartnerOffers\Services;

use App\Models\Merchant;
use App\Modules\PartnerOffers\Constants\OfferStatus;
use App\Modules\PartnerOffers\Models\PartnerOffer;
use App\Modules\PartnerOffers\Models\PartnerOfferClaim;
use App\Modules\PartnerOffers\Models\PartnerOfferImpression;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves which partner offers to show on a merchant's digital bill page.
 * Checks the full switch hierarchy: merchant master → partnership flag → offer status → attachment active.
 *
 * Owner module: PartnerOffers
 * Reads: merchants, partner_offers, partner_offer_attachments, partner_offer_network_publications,
 *        partnerships, partnership_participants, network_memberships
 * Writes: partner_offer_impressions, partner_offer_claims
 */
class BillOffersService
{
    /**
     * Check if a merchant has bill offers enabled AND has offers to show.
     * Cached for 60 seconds for the eWards /enabled lightweight check.
     */
    public function isEnabled(string $merchantUuid): array
    {
        return Cache::remember("bill_offers_enabled:{$merchantUuid}", 60, function () use ($merchantUuid) {
            $merchant = Merchant::where('uuid', $merchantUuid)->first();
            if (!$merchant || !$merchant->bill_offers_enabled) {
                return ['enabled' => false, 'offers_count' => 0, 'display_mode' => 'simple'];
            }

            $count = $this->getOffersForMerchant($merchantUuid)->count();

            return [
                'enabled'      => $count > 0,
                'offers_count' => $count,
                'display_mode' => $merchant->bill_offers_display_mode ?? 'simple',
            ];
        });
    }

    /**
     * Get all active, non-expired partner offers for a merchant.
     * Checks partnership attachments AND network publications.
     */
    public function getOffersForMerchant(string $merchantUuid): Collection
    {
        $merchant = Merchant::where('uuid', $merchantUuid)->first();
        if (!$merchant || !$merchant->bill_offers_enabled) {
            return collect();
        }

        $merchantId = $merchant->id;

        // Channel A: Partnership-level offers
        $partnershipOfferIds = DB::table('partner_offer_attachments as poa')
            ->join('partnerships as p', 'p.id', '=', 'poa.partnership_id')
            ->join('partnership_participants as pp', function ($join) use ($merchantId) {
                $join->on('pp.partnership_id', '=', 'p.id')
                     ->where('pp.merchant_id', $merchantId)
                     ->where('pp.bill_offers_enabled', true)
                     ->whereNull('pp.deleted_at');
            })
            ->where('p.status', PartnershipStatus::LIVE)
            ->whereNull('p.deleted_at')
            ->where('poa.is_active', true)
            ->pluck('poa.offer_id');

        // Channel B: Network-level offers
        $networkOfferIds = DB::table('partner_offer_network_publications as ponp')
            ->join('network_memberships as nm', function ($join) use ($merchantId) {
                $join->on('nm.network_id', '=', 'ponp.network_id')
                     ->where('nm.merchant_id', $merchantId)
                     ->where('nm.status', 1);
            })
            ->join('partner_offers as po', function ($join) use ($merchantId) {
                $join->on('po.id', '=', 'ponp.offer_id')
                     ->where('po.merchant_id', '!=', $merchantId);
            })
            ->where('ponp.is_active', true)
            ->pluck('ponp.offer_id');

        $allOfferIds = $partnershipOfferIds->merge($networkOfferIds)->unique();

        if ($allOfferIds->isEmpty()) {
            return collect();
        }

        return PartnerOffer::whereIn('id', $allOfferIds)
            ->where('status', OfferStatus::ACTIVE)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()))
            ->whereNull('deleted_at')
            ->with('merchant:id,name,category,city')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Record impressions (bulk — called when the page loads).
     */
    public function recordImpressions(int $merchantId, array $offerIds, ?string $sessionId = null): void
    {
        $now = now();
        $rows = array_map(fn ($id) => [
            'offer_id'    => $id,
            'merchant_id' => $merchantId,
            'shown_at'    => $now,
            'session_id'  => $sessionId,
        ], $offerIds);

        PartnerOfferImpression::insert($rows);
    }

    /**
     * Record a claim (coupon code copied).
     */
    public function recordClaim(int $offerId, int $merchantId, ?string $customerPhone = null): void
    {
        PartnerOfferClaim::create([
            'offer_id'       => $offerId,
            'merchant_id'    => $merchantId,
            'customer_phone' => $customerPhone,
            'claimed_at'     => now(),
        ]);
    }

    /**
     * Get impression + claim stats for an offer.
     */
    public function getOfferStats(int $offerId): array
    {
        return [
            'impressions' => PartnerOfferImpression::where('offer_id', $offerId)->count(),
            'claims'      => PartnerOfferClaim::where('offer_id', $offerId)->count(),
        ];
    }
}
