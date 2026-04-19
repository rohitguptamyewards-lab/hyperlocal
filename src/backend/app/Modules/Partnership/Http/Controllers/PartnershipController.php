<?php

namespace App\Modules\Partnership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Http\Requests\CreatePartnershipRequest;
use App\Modules\Partnership\Http\Requests\UpdatePartnershipRequest;
use App\Modules\Partnership\Http\Resources\PartnershipResource;
use App\Modules\Partnership\Constants\PartnershipTC;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Models\PartnershipAgreement;
use App\Modules\Partnership\Models\PartnershipAlert;
use App\Modules\Partnership\Models\PartnershipParticipant;
use App\Modules\Partnership\Services\PartnershipService;
use App\Modules\RulesEngine\Constants\CustomerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PartnershipController extends Controller
{
    public function __construct(private readonly PartnershipService $service) {}

    /**
     * Return the current standard T&C text and version.
     * Called by frontend before create/accept to display to users.
     */
    public function showTC(): JsonResponse
    {
        return response()->json([
            'version' => PartnershipTC::VERSION,
            'text'    => PartnershipTC::text(),
        ]);
    }

    /**
     * List all partnerships for the authenticated merchant.
     * Supports ?status= filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Partnership::forMerchant($request->user()->merchant_id)
            ->with(['participants.merchant', 'participants.outlet', 'terms'])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', (int) $request->status);
        }

        return PartnershipResource::collection($query->paginate(20));
    }

    /**
     * Create a new partnership proposal.
     */
    public function store(CreatePartnershipRequest $request): JsonResponse
    {
        $this->authorize('create', Partnership::class);

        $partnership = $this->service->create($request->user(), $request->validated());

        // Record proposer's T&C acceptance
        PartnershipAgreement::create([
            'partnership_id' => $partnership->id,
            'merchant_id'    => $request->user()->merchant_id,
            'version'        => PartnershipTC::VERSION,
            'accepted_by'    => $request->user()->id,
            'accepted_at'    => now(),
            'ip_address'     => $request->ip(),
            'created_by'     => $request->user()->id,
            'updated_by'     => $request->user()->id,
        ]);

        return (new PartnershipResource(
            $partnership->fresh(['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'])
        ))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single partnership by UUID.
     */
    public function show(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)
            ->with(['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'])
            ->firstOrFail();

        $this->authorize('view', $partnership);

        return new PartnershipResource($partnership);
    }

    /**
     * Update terms/rules while in an editable status.
     * Automatically moves status to NEGOTIATING when terms/rules change.
     * Notifies the OTHER party so they can review the counter-offer.
     */
    public function update(UpdatePartnershipRequest $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $partnership);

        $wasNegotiating = in_array($partnership->status, [
            PartnershipStatus::REQUESTED,
            PartnershipStatus::NEGOTIATING,
        ], true);

        $hasTermsOrRules = !empty($request->validated()['terms'])
            || !empty($request->validated()['rules']);

        $partnership = $this->service->update($request->user(), $partnership, $request->validated());

        // Notify the OTHER participant that terms/offer have been updated
        if ($wasNegotiating && $hasTermsOrRules) {
            $myMerchantId = $request->user()->merchant_id;

            $otherParticipant = PartnershipParticipant::where('partnership_id', $partnership->id)
                ->where('merchant_id', '!=', $myMerchantId)
                ->whereNull('deleted_at')
                ->first();

            if ($otherParticipant) {
                $myName = \App\Models\Merchant::where('id', $myMerchantId)->value('name') ?? 'Your partner';

                // Remove previous unread terms alert for this partnership to avoid stacking
                PartnershipAlert::where('partnership_id', $partnership->id)
                    ->where('recipient_merchant_id', $otherParticipant->merchant_id)
                    ->where('type', PartnershipAlert::TYPE_TERMS_UPDATED)
                    ->whereNull('read_at')
                    ->delete();

                PartnershipAlert::create([
                    'partnership_id'        => $partnership->id,
                    'recipient_merchant_id' => $otherParticipant->merchant_id,
                    'type'                  => PartnershipAlert::TYPE_TERMS_UPDATED,
                    'title'                 => "{$myName} proposed new terms",
                    'body'                  => "{$myName} has updated the terms for \"{$partnership->name}\". Review them and accept or suggest further changes.",
                ]);
            }
        }

        return new PartnershipResource($partnership);
    }

    /**
     * Acceptor approves the partnership request.
     */
    public function accept(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('accept', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::AGREED
        );

        // Record acceptor's T&C acceptance
        PartnershipAgreement::create([
            'partnership_id' => $partnership->id,
            'merchant_id'    => $request->user()->merchant_id,
            'version'        => PartnershipTC::VERSION,
            'accepted_by'    => $request->user()->id,
            'accepted_at'    => now(),
            'ip_address'     => $request->ip(),
            'created_by'     => $request->user()->id,
            'updated_by'     => $request->user()->id,
        ]);

        return new PartnershipResource(
            $partnership->fresh(['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'])
        );
    }

    /**
     * Either party rejects the partnership.
     */
    public function reject(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('reject', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::REJECTED
        );

        return new PartnershipResource($partnership);
    }

    /**
     * Acceptor accepts and immediately starts the partnership in one step.
     * Skips the AGREED intermediate state — transitions directly to LIVE.
     * Fires PartnershipLive event (creates enablement rows, etc.)
     */
    public function acceptAndGoLive(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('acceptAndGoLive', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::LIVE
        );

        // Record acceptor's T&C acceptance
        PartnershipAgreement::create([
            'partnership_id' => $partnership->id,
            'merchant_id'    => $request->user()->merchant_id,
            'version'        => PartnershipTC::VERSION,
            'accepted_by'    => $request->user()->id,
            'accepted_at'    => now(),
            'ip_address'     => $request->ip(),
            'created_by'     => $request->user()->id,
            'updated_by'     => $request->user()->id,
        ]);

        return new PartnershipResource(
            $partnership->fresh(['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'])
        );
    }

    /**
     * Go live — transition AGREED → LIVE.
     */
    public function goLive(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('goLive', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::LIVE
        );

        return new PartnershipResource($partnership);
    }

    /**
     * Pause a LIVE partnership.
     */
    public function pause(Request $request, string $uuid): PartnershipResource
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('pause', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::PAUSED,
            ['paused_at' => now(), 'paused_reason' => $request->reason]
        );

        return new PartnershipResource($partnership);
    }

    /**
     * Paginated redemption history for a partnership.
     * Returns all redemptions visible to the authenticated merchant (their own merchant_id only).
     * Joined with partner_claims for token and outlets for outlet name.
     *
     * Query params: ?page=1&per_page=20&status=1
     */
    public function redemptions(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $merchantId = $request->user()->merchant_id;
        $perPage    = min((int) ($request->per_page ?? 20), 100);

        $query = DB::table('partner_redemptions as r')
            ->join('partner_claims as c', 'c.id', '=', 'r.claim_id')
            ->join('outlets as o', 'o.id', '=', 'r.outlet_id')
            ->where('r.partnership_id', $partnership->id)
            ->where('r.merchant_id', $merchantId)
            ->select([
                'r.uuid',
                'r.created_at',
                'c.token',
                'o.name as outlet_name',
                'r.bill_amount',
                'r.benefit_amount',
                'r.customer_type',
                'r.approval_method',
                'r.status',
            ])
            ->orderByDesc('r.created_at');

        if ($request->filled('status')) {
            $query->where('r.status', (int) $request->status);
        }

        $paginator = $query->paginate($perPage);

        $statusLabels    = [1 => 'Completed', 2 => 'Reversed', 3 => 'Disputed'];
        $approvalLabels  = [1 => 'Auto', 2 => 'Manager', 3 => 'OTP'];

        $items = collect($paginator->items())->map(fn ($row) => [
            'id'                  => $row->uuid,
            'date'                => $row->created_at,
            'token'               => $row->token,
            'outlet_name'         => $row->outlet_name,
            'bill_amount'         => (float) $row->bill_amount,
            'benefit_amount'      => (float) $row->benefit_amount,
            'customer_type'       => $row->customer_type,
            'customer_type_label' => CustomerType::label($row->customer_type),
            'approval_method'     => $row->approval_method,
            'approval_label'      => $approvalLabels[$row->approval_method] ?? 'Auto',
            'status'              => $row->status,
            'status_label'        => $statusLabels[$row->status] ?? 'Unknown',
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Return the target-side outlets for a LIVE partnership.
     * Used by the claim-issuance UI to populate the target outlet picker.
     * Only returns outlets belonging to the *other* participant (the target merchant).
     */
    public function partnerOutlets(Request $request, string $uuid): JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)
            ->with('participants.outlet')
            ->firstOrFail();

        $this->authorize('view', $partnership);

        $myMerchantId = $request->user()->merchant_id;

        $outlets = collect();
        foreach ($partnership->participants as $participant) {
            if ((int) $participant->merchant_id === (int) $myMerchantId) {
                continue; // skip own outlets
            }
            if ($participant->outlet_id !== null) {
                $outlets->push([
                    'id'       => $participant->outlet_id,
                    'name'     => optional($participant->outlet)->name ?? "Outlet #{$participant->outlet_id}",
                    'address'  => optional($participant->outlet)->address,
                ]);
            } else {
                // brand-wide — fetch all active outlets for that merchant
                $partnerOutlets = \App\Models\Outlet::where('merchant_id', $participant->merchant_id)
                    ->where('is_active', true)
                    ->get(['id', 'name', 'address']);
                foreach ($partnerOutlets as $o) {
                    $outlets->push(['id' => $o->id, 'name' => $o->name, 'address' => $o->address]);
                }
            }
        }

        return response()->json($outlets->unique('id')->values());
    }

    /**
     * Acceptor (or proposer) fills in their own offer config when offer_structure = 'different'.
     * POST /api/partnerships/{uuid}/fill-offer
     */
    public function fillOffer(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'pos_type'        => ['required', 'string', 'in:flat,percentage'],
            'flat_amount'     => ['nullable', 'numeric', 'min:0', 'required_if:pos_type,flat'],
            'percentage'      => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:pos_type,percentage'],
            'max_cap'         => ['nullable', 'numeric', 'min:0'],
            'min_bill'        => ['nullable', 'numeric', 'min:0'],
            'monthly_cap'     => ['nullable', 'numeric', 'min:0'],
            'linked_offer_id' => ['nullable', 'integer', 'exists:partner_offers,id'],
        ]);

        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $partnership);

        $myMerchantId = $request->user()->merchant_id;

        // Check BEFORE saving whether the current user already had an offer filled
        $myParticipant = PartnershipParticipant::where('partnership_id', $partnership->id)
            ->where('merchant_id', $myMerchantId)
            ->whereNull('deleted_at')
            ->first();
        $wasAlreadyFilled = (bool) ($myParticipant?->offer_filled ?? false);

        $this->service->saveParticipantOffer($partnership->id, $myMerchantId, $data);

        // Notify the PROPOSER so they know the acceptor has filled their offer
        $proposerParticipant = PartnershipParticipant::where('partnership_id', $partnership->id)
            ->where('role', \App\Modules\Partnership\Constants\ParticipantRole::PROPOSER)
            ->whereNull('deleted_at')
            ->first();

        if ($proposerParticipant && $proposerParticipant->merchant_id !== $myMerchantId) {
            $myMerchantName = \App\Models\Merchant::where('id', $myMerchantId)->value('name') ?? 'Your partner';

            // Delete any previous unread offer-filled alert for this partnership to avoid duplicates
            PartnershipAlert::where('partnership_id', $partnership->id)
                ->where('recipient_merchant_id', $proposerParticipant->merchant_id)
                ->whereIn('type', [PartnershipAlert::TYPE_OFFER_FILLED, PartnershipAlert::TYPE_OFFER_UPDATED])
                ->whereNull('read_at')
                ->delete();

            $isUpdate = $wasAlreadyFilled;  // true if the acceptor already had an offer filled before this update
            PartnershipAlert::create([
                'partnership_id'        => $partnership->id,
                'recipient_merchant_id' => $proposerParticipant->merchant_id,
                'type'                  => $isUpdate ? PartnershipAlert::TYPE_OFFER_UPDATED : PartnershipAlert::TYPE_OFFER_FILLED,
                'title'                 => $isUpdate
                    ? "{$myMerchantName} updated their offer"
                    : "{$myMerchantName} has filled in their offer",
                'body'                  => $isUpdate
                    ? "{$myMerchantName} has updated the offer they're providing to your customers for partnership \"{$partnership->name}\". Review it before accepting."
                    : "{$myMerchantName} has filled in their offer details for the partnership \"{$partnership->name}\". You can now review and proceed.",
            ]);
        }

        return response()->json(['message' => 'Offer saved.']);
    }

    /**
     * Resume a PAUSED partnership.
     */
    public function resume(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();

        $this->authorize('resume', $partnership);

        $partnership = $this->service->transition(
            $request->user(),
            $partnership,
            PartnershipStatus::LIVE,
            ['paused_at' => null, 'paused_reason' => null]
        );

        return new PartnershipResource($partnership);
    }

    /**
     * Trigger a customer notification broadcast about a partnership settings change.
     * Mocked locally via Log — replace with WhatsApp/SMS gateway when available.
     *
     * The message is sent to this merchant's customer base only — not the partner's.
     * Payload echoes the current state so the template is accurate.
     */
    public function notifyCustomers(Request $request, string $uuid): \Illuminate\Http\JsonResponse
    {
        $partnership = Partnership::where('uuid', $uuid)->firstOrFail();
        $this->authorize('updateMySettings', $partnership);

        $data = $request->validate([
            'issuing_enabled'    => ['required', 'boolean'],
            'redemption_enabled' => ['required', 'boolean'],
        ]);

        // Mock: in production, trigger a WhatsApp broadcast to this merchant's customer base
        \Illuminate\Support\Facades\Log::info('Partnership customer notification', [
            'partnership_uuid' => $uuid,
            'merchant_id'      => $request->user()->merchant_id,
            'issuing_enabled'  => $data['issuing_enabled'],
            'redemption_enabled' => $data['redemption_enabled'],
            'note'             => 'Replace with real WhatsApp/SMS broadcast when gateway is wired.',
        ]);

        return response()->json(['queued' => true, 'note' => 'Notification queued (mocked).']);
    }

    /**
     * Update the calling merchant's per-side permission flags for this partnership.
     *
     * Accepts any combination of:
     *   issuing_enabled    — can my cashiers issue tokens?
     *   redemption_enabled — can my outlets redeem tokens?
     *   campaigns_enabled  — can the partner send campaigns to my customer base?
     *
     * The master on/off is still partnership status (pause/resume).
     * These flags give granular control within a LIVE partnership.
     */
    public function updateMySettings(Request $request, string $uuid): PartnershipResource
    {
        $partnership = Partnership::with(['participants.merchant', 'participants.outlet', 'terms', 'rules'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('updateMySettings', $partnership);

        $data = $request->validate([
            'issuing_enabled'    => ['sometimes', 'boolean'],
            'redemption_enabled' => ['sometimes', 'boolean'],
            'campaigns_enabled'    => ['sometimes', 'boolean'],
            'bill_offers_enabled'  => ['sometimes', 'boolean'],
        ]);

        if (empty($data)) {
            return new PartnershipResource($partnership);
        }

        $data['updated_by'] = $request->user()->id;

        // Derive legacy suspended_at from the flags for backward compatibility
        if (isset($data['issuing_enabled']) || isset($data['redemption_enabled'])) {
            $participant = $partnership->participants
                ->where('merchant_id', $request->user()->merchant_id)
                ->first();

            $issuingWillBeEnabled    = $data['issuing_enabled']    ?? (bool) ($participant?->issuing_enabled ?? true);
            $redemptionWillBeEnabled = $data['redemption_enabled'] ?? (bool) ($participant?->redemption_enabled ?? true);

            if (!$issuingWillBeEnabled && !$redemptionWillBeEnabled) {
                $data['suspended_at']      = now();
                $data['suspension_reason'] = 'Both issuing and redemption disabled via settings';
            } elseif ($issuingWillBeEnabled && $redemptionWillBeEnabled) {
                $data['suspended_at']      = null;
                $data['suspension_reason'] = null;
            }
        }

        $partnership->participants()
            ->where('merchant_id', $request->user()->merchant_id)
            ->update($data);

        return new PartnershipResource(
            $partnership->fresh(['participants.merchant', 'participants.outlet', 'terms', 'rules', 'agreements'])
        );
    }
}
