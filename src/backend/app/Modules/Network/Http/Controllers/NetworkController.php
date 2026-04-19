<?php

namespace App\Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Modules\Network\Models\HyperlocalNetwork;
use App\Modules\Network\Models\NetworkInvitation;
use App\Modules\Network\Models\NetworkMembership;
use App\Modules\Network\Services\NetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant-facing endpoints for hyperlocal networks.
 *
 * GET    /api/merchant/networks                — list networks this merchant belongs to
 * POST   /api/merchant/networks                — create a new network
 * GET    /api/merchant/networks/{uuid}         — get network + member list
 * POST   /api/merchant/networks/{uuid}/leave   — leave a network (non-owners only)
 * POST   /api/merchant/networks/{uuid}/invite  — send invitation (owner only)
 * POST   /api/merchant/networks/join/{token}   — accept an invitation by token
 *
 * Owner module: Network
 */
class NetworkController extends Controller
{
    public function __construct(
        private readonly NetworkService $service,
    ) {}

    /**
     * GET /api/merchant/networks
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->listForMerchant($request->user()->merchant_id);

        return response()->json([
            'data' => $paginated->map(fn ($n) => $this->formatNetwork($n)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /api/merchant/networks
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $network = $this->service->create(
            merchantId:  $request->user()->merchant_id,
            createdBy:   $request->user()->id,
            name:        $data['name'],
            description: $data['description'] ?? null,
        );

        return response()->json([
            'message' => 'Network created.',
            'network' => $this->formatNetwork($network),
        ], 201);
    }

    /**
     * GET /api/merchant/networks/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $network = $this->service->getForMerchant($uuid, $request->user()->merchant_id);

        $members = NetworkMembership::where('network_id', $network->id)
            ->where('status', NetworkMembership::STATUS_ACTIVE)
            ->join('merchants', 'merchants.id', '=', 'network_memberships.merchant_id')
            ->select('network_memberships.*', 'merchants.name as merchant_name')
            ->get()
            ->map(fn ($m) => [
                'merchant_id'   => $m->merchant_id,
                'merchant_name' => $m->merchant_name,
                'joined_at'     => $m->joined_at?->toIso8601String(),
                'is_owner'      => $m->merchant_id === $network->owner_merchant_id,
            ]);

        return response()->json([
            'network' => array_merge($this->formatNetwork($network), ['members' => $members]),
        ]);
    }

    /**
     * POST /api/merchant/networks/{uuid}/invite
     */
    public function invite(Request $request, string $uuid): JsonResponse
    {
        $network = HyperlocalNetwork::where('uuid', $uuid)->firstOrFail();

        // Only the owner may invite
        if ($network->owner_merchant_id !== $request->user()->merchant_id) {
            return response()->json(['error' => 'Only the network owner can send invitations.'], 403);
        }

        $data = $request->validate([
            'channel' => ['required', 'string', 'in:email,whatsapp,link'],
            'contact' => ['nullable', 'string', 'max:200'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $invitation = $this->service->invite(
            network:   $network,
            invitedBy: $request->user()->id,
            channel:   $data['channel'],
            contact:   $data['contact'] ?? null,
            maxUses:   $data['max_uses'] ?? null,
        );

        return response()->json([
            'message'    => 'Invitation created.',
            'token'      => $invitation->token,
            'uuid'       => $invitation->uuid,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'max_uses'   => $invitation->max_uses,
            'remaining_uses' => $invitation->max_uses !== null
                ? max($invitation->max_uses - $invitation->uses_count, 0)
                : null,
        ], 201);
    }

    /**
     * POST /api/merchant/networks/join/{token}
     */
    public function join(Request $request, string $token): JsonResponse
    {
        $membership = $this->service->accept($token, $request->user()->merchant_id);

        $network = HyperlocalNetwork::find($membership->network_id);

        return response()->json([
            'message'     => 'You have joined the network.',
            'network_uuid' => $network?->uuid,
            'network_name' => $network?->name,
            'joined_at'   => $membership->joined_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/merchant/networks/{uuid}/leave
     */
    public function leave(Request $request, string $uuid): JsonResponse
    {
        $network = HyperlocalNetwork::where('uuid', $uuid)->firstOrFail();

        $this->service->leave($network, $request->user()->merchant_id);

        return response()->json(['message' => 'You have left the network.']);
    }

    /**
     * GET /api/public/network-invite/{token}  (no auth required)
     *
     * Returns network details for the invite landing page without joining.
     * Used so an unauthenticated brand can see what network they've been invited to
     * before deciding to login / register.
     */
    public function previewInvite(string $token): JsonResponse
    {
        $invitation = NetworkInvitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['error' => 'Invitation not found.'], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json(['error' => 'This invitation link has expired or been used up.'], 410);
        }

        $network = HyperlocalNetwork::find($invitation->network_id);

        if (!$network || !$network->isActive()) {
            return response()->json(['error' => 'This network is no longer active.'], 410);
        }

        $ownerName = Merchant::where('id', $network->owner_merchant_id)->value('name') ?? 'Unknown Brand';

        $memberCount = NetworkMembership::where('network_id', $network->id)
            ->where('status', NetworkMembership::STATUS_ACTIVE)
            ->count();

        return response()->json([
            'network' => [
                'uuid'         => $network->uuid,
                'name'         => $network->name,
                'description'  => $network->description,
                'owner_name'   => $ownerName,
                'member_count' => $memberCount,
            ],
            'invitation' => [
                'token'          => $invitation->token,
                'channel'        => $invitation->invite_channel,
                'remaining_uses' => $invitation->max_uses !== null
                    ? max($invitation->max_uses - $invitation->uses_count, 0)
                    : null,
                'expires_at'     => $invitation->expires_at?->toIso8601String(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatNetwork(HyperlocalNetwork $n): array
    {
        return [
            'uuid'               => $n->uuid,
            'name'               => $n->name,
            'slug'               => $n->slug,
            'description'        => $n->description,
            'owner_merchant_id'  => $n->owner_merchant_id,
            'status'             => $n->status,
            'members_count'      => $n->memberships_count ?? null,
            'created_at'         => $n->created_at->toIso8601String(),
        ];
    }
}
