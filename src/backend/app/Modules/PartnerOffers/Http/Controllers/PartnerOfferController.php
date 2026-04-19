<?php

namespace App\Modules\PartnerOffers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Network\Models\HyperlocalNetwork;
use App\Modules\PartnerOffers\Http\Requests\CreateOfferRequest;
use App\Modules\PartnerOffers\Models\PartnerOffer;
use App\Modules\PartnerOffers\Services\BillOffersService;
use App\Modules\PartnerOffers\Services\PartnerOfferService;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated CRUD for partner offers (merchant dashboard).
 * Owner module: PartnerOffers
 */
class PartnerOfferController extends Controller
{
    public function __construct(
        private readonly PartnerOfferService $offers,
        private readonly BillOffersService   $billOffers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->offers->listForMerchant($request->user()->merchant_id),
        ]);
    }

    public function store(CreateOfferRequest $request): JsonResponse
    {
        $offer = $this->offers->create($request->user(), $request->validated());
        return response()->json(['data' => $offer], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $offer = PartnerOffer::where('uuid', $uuid)
            ->with(['attachments.partnership:id,uuid,name', 'networkPublications.network:id,uuid,name'])
            ->firstOrFail();

        $stats = $this->billOffers->getOfferStats($offer->id);

        return response()->json([
            'data'  => $offer,
            'stats' => $stats,
        ]);
    }

    public function update(CreateOfferRequest $request, string $uuid): JsonResponse
    {
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $updated = $this->offers->update($request->user(), $offer, $request->validated());
        return response()->json(['data' => $updated]);
    }

    public function toggle(Request $request, string $uuid): JsonResponse
    {
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $toggled = $this->offers->toggleStatus($request->user(), $offer);
        return response()->json(['data' => $toggled]);
    }

    public function attach(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['partnership_id' => ['required', 'string']]);
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $attachment = $this->offers->attachToPartnership(
            $request->user(),
            $offer,
            $this->resolvePartnershipId($data['partnership_id']),
        );
        return response()->json(['data' => $attachment->load('partnership:id,uuid,name')], 201);
    }

    public function detach(string $uuid, string $partnershipId): JsonResponse
    {
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $this->offers->detachFromPartnership($offer->id, $this->resolvePartnershipId($partnershipId));
        return response()->json(['message' => 'Detached.']);
    }

    public function publish(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['network_id' => ['required', 'string']]);
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $pub = $this->offers->publishToNetwork(
            $request->user(),
            $offer,
            $this->resolveNetworkId($data['network_id']),
        );
        return response()->json(['data' => $pub->load('network:id,uuid,name')], 201);
    }

    public function unpublish(string $uuid, string $networkId): JsonResponse
    {
        $offer = PartnerOffer::where('uuid', $uuid)->firstOrFail();
        $this->offers->unpublishFromNetwork($offer->id, $this->resolveNetworkId($networkId));
        return response()->json(['message' => 'Unpublished.']);
    }

    public function availableForPartnership(Request $request, string $partnershipUuid): JsonResponse
    {
        $partnership = \App\Modules\Partnership\Models\Partnership::where('uuid', $partnershipUuid)->firstOrFail();
        $offers = $this->offers->availableForPartnership($partnership->id, $request->user()->merchant_id);
        return response()->json(['data' => $offers]);
    }

    public function networkOffers(Request $request, string $networkUuid): JsonResponse
    {
        $network = \App\Modules\Network\Models\HyperlocalNetwork::where('uuid', $networkUuid)->firstOrFail();

        $offers = PartnerOffer::whereHas('networkPublications', fn ($q) => $q->where('network_id', $network->id)->where('is_active', true))
            ->active()
            ->notExpired()
            ->where('merchant_id', '!=', $request->user()->merchant_id)
            ->with('merchant:id,name,category')
            ->get();

        return response()->json(['data' => $offers]);
    }

    private function resolvePartnershipId(string $identifier): int
    {
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return Partnership::where('uuid', $identifier)->valueOrFail('id');
    }

    private function resolveNetworkId(string $identifier): int
    {
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return HyperlocalNetwork::where('uuid', $identifier)->valueOrFail('id');
    }
}
