<?php

namespace App\Modules\PartnerOffers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\PartnerOffers\Models\PartnerOffer;
use App\Modules\PartnerOffers\Services\BillOffersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) endpoints for the bill offers page.
 * Called by the customer's browser when viewing their digital bill.
 *
 * Owner module: PartnerOffers
 */
class BillOffersPublicController extends Controller
{
    public function __construct(private readonly BillOffersService $service) {}

    /**
     * GET /api/public/bill-offers/{merchantUuid}/enabled
     * Lightweight check for eWards integration.
     */
    public function enabled(string $merchantUuid): JsonResponse
    {
        return response()->json($this->service->isEnabled($merchantUuid));
    }

    /**
     * GET /api/public/bill-offers/{merchantUuid}
     * Full list of offers for the bill page.
     */
    public function index(string $merchantUuid): JsonResponse
    {
        $merchant = Merchant::where('uuid', $merchantUuid)->firstOrFail();
        $offers = $this->service->getOffersForMerchant($merchantUuid);

        return response()->json([
            'merchant' => [
                'name'         => $merchant->name,
                'category'     => $merchant->category,
                'display_mode' => $merchant->bill_offers_display_mode ?? 'simple',
            ],
            'offers' => $offers->map(fn ($o) => [
                'uuid'            => $o->uuid,
                'title'           => $o->title,
                'description'     => $o->description,
                'coupon_code'     => $o->coupon_code,
                'discount_type'   => $o->discount_type,
                'discount_value'  => $o->discount_value,
                'image_url'       => $o->image_url,
                'expiry_date'     => $o->expiry_date?->toDateString(),
                'terms_conditions' => $o->terms_conditions,
                'brand_name'      => $o->merchant?->name,
                'brand_category'  => $o->merchant?->category,
            ]),
            'count' => $offers->count(),
        ]);
    }

    /**
     * POST /api/public/bill-offers/{merchantUuid}/impressions
     * Record that offers were shown.
     */
    public function recordImpressions(Request $request, string $merchantUuid): JsonResponse
    {
        $data = $request->validate([
            'offer_ids'  => ['required', 'array', 'max:50'],
            'offer_ids.*' => ['integer'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $merchant = Merchant::where('uuid', $merchantUuid)->firstOrFail();
        $this->service->recordImpressions($merchant->id, $data['offer_ids'], $data['session_id'] ?? null);

        return response()->json(['recorded' => count($data['offer_ids'])]);
    }

    /**
     * POST /api/public/bill-offers/{merchantUuid}/claims/{offerUuid}
     * Record that a coupon code was copied.
     */
    public function recordClaim(Request $request, string $merchantUuid, string $offerUuid): JsonResponse
    {
        $merchant = Merchant::where('uuid', $merchantUuid)->firstOrFail();
        $offer = PartnerOffer::where('uuid', $offerUuid)->firstOrFail();

        $this->service->recordClaim($offer->id, $merchant->id, $request->input('phone'));

        return response()->json(['recorded' => true]);
    }
}
