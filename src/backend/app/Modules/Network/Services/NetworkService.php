<?php

namespace App\Modules\Network\Services;

use App\Modules\Network\Models\HyperlocalNetwork;
use App\Modules\Network\Models\NetworkInvitation;
use App\Modules\Network\Models\NetworkMembership;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages hyperlocal network lifecycle: create, invite, accept, leave.
 *
 * Key rules:
 *  - Only the owner merchant can invite others or close the network.
 *  - A merchant may belong to multiple networks simultaneously (by design).
 *  - Calculation isolation is per partnership_id, not per network —
 *    cap counters and cooling periods are unaffected by network membership.
 *  - Invitations expire after INVITATION_VALIDITY_HOURS.
 *
 * Owner module: Network
 * Tables written: hyperlocal_networks, network_memberships, network_invitations
 */
class NetworkService
{
    /** Invitation validity window in hours. */
    private const INVITATION_VALIDITY_HOURS = 72;

    /**
     * Create a new hyperlocal network owned by the given merchant.
     * The owning merchant is automatically added as a member.
     *
     * @param  int         $merchantId   Owner merchant
     * @param  int         $createdBy    User ID
     * @param  string      $name
     * @param  string|null $description
     * @return HyperlocalNetwork
     */
    public function create(int $merchantId, int $createdBy, string $name, ?string $description = null): HyperlocalNetwork
    {
        return DB::transaction(function () use ($merchantId, $createdBy, $name, $description): HyperlocalNetwork {
            $network = HyperlocalNetwork::create([
                'name'              => $name,
                'description'       => $description,
                'owner_merchant_id' => $merchantId,
                'status'            => HyperlocalNetwork::STATUS_ACTIVE,
                'created_by'        => $createdBy,
            ]);

            // Auto-add owner as a member
            NetworkMembership::create([
                'network_id'  => $network->id,
                'merchant_id' => $merchantId,
                'status'      => NetworkMembership::STATUS_ACTIVE,
                'joined_at'   => now(),
            ]);

            return $network;
        });
    }

    /**
     * Send an invitation to join the network.
     *
     * @param  HyperlocalNetwork $network
     * @param  int               $invitedBy   User ID of the person sending the invite
     * @param  string            $channel     'email' | 'whatsapp' | 'link'
     * @param  string|null       $contact     Email or phone (not required for link channel)
     * @return NetworkInvitation
     * @throws ValidationException
     */
    public function invite(
        HyperlocalNetwork $network,
        int               $invitedBy,
        string            $channel,
        ?string           $contact = null,
        ?int              $maxUses = null,
    ): NetworkInvitation {
        if (!$network->isActive()) {
            throw ValidationException::withMessages([
                'network_id' => ['Cannot invite to a network that is not active.'],
            ]);
        }

        return NetworkInvitation::create([
            'network_id'     => $network->id,
            'invited_by'     => $invitedBy,
            'invite_channel' => $channel,
            'contact'        => $contact,
            'status'         => NetworkInvitation::STATUS_PENDING,
            'max_uses'       => $maxUses ?? 10,
            'uses_count'     => 0,
            'expires_at'     => now()->addHours(self::INVITATION_VALIDITY_HOURS),
        ]);
    }

    /**
     * Accept an invitation using the invitation token.
     * Creates a NetworkMembership for the accepting merchant.
     *
     * @param  string $token      Invitation token from URL
     * @param  int    $merchantId Accepting merchant's ID
     * @return NetworkMembership
     * @throws ValidationException
     */
    public function accept(string $token, int $merchantId): NetworkMembership
    {
        $invitation = NetworkInvitation::where('token', $token)
            ->where('status', NetworkInvitation::STATUS_PENDING)
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is invalid or has expired.'],
            ]);
        }

        // Check merchant is not already a member
        $alreadyMember = NetworkMembership::where('network_id', $invitation->network_id)
            ->where('merchant_id', $merchantId)
            ->where('status', NetworkMembership::STATUS_ACTIVE)
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'merchant_id' => ['You are already a member of this network.'],
            ]);
        }

        return DB::transaction(function () use ($invitation, $merchantId): NetworkMembership {
            $nextUsesCount = $invitation->uses_count + 1;

            $membership = NetworkMembership::create([
                'network_id'  => $invitation->network_id,
                'merchant_id' => $merchantId,
                'status'      => NetworkMembership::STATUS_ACTIVE,
                'invited_by'  => $invitation->invited_by,
                'joined_at'   => now(),
            ]);

            $invitation->update([
                'status'      => $invitation->max_uses !== null && $nextUsesCount >= $invitation->max_uses
                    ? NetworkInvitation::STATUS_ACCEPTED
                    : NetworkInvitation::STATUS_PENDING,
                'uses_count'  => $nextUsesCount,
                'merchant_id' => $merchantId,
                'accepted_at' => now(),
            ]);

            return $membership;
        });
    }

    /**
     * List all networks a merchant belongs to (active memberships).
     *
     * @param  int $merchantId
     * @return LengthAwarePaginator
     */
    public function listForMerchant(int $merchantId): LengthAwarePaginator
    {
        return HyperlocalNetwork::whereHas('memberships', function ($q) use ($merchantId): void {
            $q->where('merchant_id', $merchantId)
              ->where('status', NetworkMembership::STATUS_ACTIVE);
        })
        ->withCount(['memberships' => function ($q): void {
            $q->where('status', NetworkMembership::STATUS_ACTIVE);
        }])
        ->orderByDesc('created_at')
        ->paginate(20);
    }

    /**
     * Get a single network with its active members (paginated).
     * Merchant must be a member to view.
     *
     * @param  string $uuid
     * @param  int    $merchantId  Requesting merchant — must be a member
     * @return HyperlocalNetwork
     * @throws ValidationException
     */
    public function getForMerchant(string $uuid, int $merchantId): HyperlocalNetwork
    {
        $network = HyperlocalNetwork::where('uuid', $uuid)->firstOrFail();

        $isMember = NetworkMembership::where('network_id', $network->id)
            ->where('merchant_id', $merchantId)
            ->where('status', NetworkMembership::STATUS_ACTIVE)
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'network_id' => ['You are not a member of this network.'],
            ]);
        }

        return $network->load(['memberships' => function ($q): void {
            $q->where('status', NetworkMembership::STATUS_ACTIVE)
              ->with('network');
        }]);
    }

    /**
     * Leave a network. Owners cannot leave — they must close the network instead.
     *
     * @param  HyperlocalNetwork $network
     * @param  int               $merchantId  Leaving merchant
     * @throws ValidationException
     */
    public function leave(HyperlocalNetwork $network, int $merchantId): void
    {
        if ($network->owner_merchant_id === $merchantId) {
            throw ValidationException::withMessages([
                'network_id' => ['Network owners cannot leave. Close the network instead.'],
            ]);
        }

        $membership = NetworkMembership::where('network_id', $network->id)
            ->where('merchant_id', $merchantId)
            ->where('status', NetworkMembership::STATUS_ACTIVE)
            ->first();

        if (!$membership) {
            throw ValidationException::withMessages([
                'network_id' => ['You are not an active member of this network.'],
            ]);
        }

        $membership->update(['status' => NetworkMembership::STATUS_LEFT]);
    }
}
