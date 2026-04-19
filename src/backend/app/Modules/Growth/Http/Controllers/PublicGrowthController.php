<?php

namespace App\Modules\Growth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\Growth\Services\ReferralService;
use App\Modules\PartnerOffers\Constants\OfferStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public endpoints: brand profiles (#11), referral link redirect (#1),
 * offer marketplace (#13).
 */
class PublicGrowthController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    /**
     * GET /api/public/brand/{slug}
     * Public brand profile.
     */
    public function brandProfile(string $slug): JsonResponse
    {
        $merchant = Merchant::where('slug', $slug)
            ->where('profile_public', true)
            ->firstOrFail();

        $activeOffers = DB::table('partner_offers')
            ->where('merchant_id', $merchant->id)
            ->where('status', OfferStatus::ACTIVE)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()))
            ->whereNull('deleted_at')
            ->select(['uuid', 'title', 'description', 'coupon_code', 'discount_type', 'discount_value', 'expiry_date'])
            ->get();

        $partnershipCount = DB::table('partnership_participants')
            ->where('merchant_id', $merchant->id)
            ->join('partnerships', 'partnerships.id', '=', 'partnership_participants.partnership_id')
            ->where('partnerships.status', 5)
            ->count();

        return response()->json([
            'brand' => [
                'name'     => $merchant->name,
                'category' => $merchant->category,
                'city'     => $merchant->city,
                'bio'      => $merchant->bio,
                'logo_url' => $merchant->logo_url,
            ],
            'active_offers'     => $activeOffers,
            'partnership_count' => $partnershipCount,
        ]);
    }

    /**
     * GET /api/public/r/{code}
     * Referral link redirect — records click and returns partnership info.
     */
    public function referralRedirect(string $code): JsonResponse
    {
        $result = $this->referrals->recordClick($code);

        if (!$result) {
            return response()->json(['error' => 'Invalid referral link.'], 404);
        }

        $partnership = DB::table('partnerships')
            ->where('id', $result['partnership_id'])
            ->first(['uuid', 'name']);

        return response()->json([
            'partnership_uuid' => $partnership?->uuid,
            'partnership_name' => $partnership?->name,
            'redirect_to'      => '/bill-offers/' . DB::table('merchants')->where('id', $result['merchant_id'])->value('uuid'),
        ]);
    }

    /**
     * GET /api/public/marketplace
     * Cross-network offer marketplace (#13) — trending offers.
     */
    public function marketplace(Request $request): JsonResponse
    {
        $city = $request->query('city', 'Mumbai');

        $offers = DB::table('partner_offers as po')
            ->join('merchants as m', 'm.id', '=', 'po.merchant_id')
            ->where('po.status', OfferStatus::ACTIVE)
            ->where(fn ($q) => $q->whereNull('po.expiry_date')->orWhere('po.expiry_date', '>=', now()->toDateString()))
            ->whereNull('po.deleted_at')
            ->where('m.city', $city)
            ->orderByDesc('po.created_at')
            ->limit(20)
            ->select([
                'po.uuid', 'po.title', 'po.description', 'po.coupon_code',
                'po.discount_type', 'po.discount_value', 'po.expiry_date',
                'm.name as brand_name', 'm.category as brand_category', 'm.slug',
            ])
            ->get();

        return response()->json(['offers' => $offers, 'city' => $city]);
    }
}
