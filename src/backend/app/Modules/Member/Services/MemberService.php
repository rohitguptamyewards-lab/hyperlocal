<?php

namespace App\Modules\Member\Services;

use App\Modules\Member\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Manages member identity lifecycle.
 *
 * Owner module: Member
 * Tables owned: members, member_integrations
 * Called by: ClaimService, PublicClaimController, CampaignService, CouponService
 */
class MemberService
{
    /**
     * Find or create a member by phone number.
     * Updates name if provided and member doesn't have one yet.
     * Always touches last_seen_at.
     *
     * @param  string      $phone Normalised phone (digits only, with country code, no spaces)
     * @param  string|null $name  Optional display name from QR form submission
     * @return Member
     */
    public function findOrCreateByPhone(string $phone, ?string $name = null): Member
    {
        $member = Member::where('phone', $phone)->first();

        if ($member) {
            $updates = ['last_seen_at' => now()];
            if ($name && !$member->name) {
                $updates['name'] = $name;
            }
            $member->update($updates);
            return $member->fresh();
        }

        return Member::create([
            'phone'           => $phone,
            'name'            => $name,
            'whatsapp_opt_in' => true,
            'last_seen_at'    => now(),
        ]);
    }

    /**
     * Link a member to an external provider identity.
     * Upserts — safe to call on every sync.
     *
     * @param  Member $member
     * @param  string $provider    e.g. 'ewrds', 'capillary', 'pos_xyz'
     * @param  string $externalId  Member ID on the external system
     * @param  array  $meta        Optional extra data from the external system
     */
    public function linkExternal(Member $member, string $provider, string $externalId, array $meta = []): void
    {
        DB::table('member_integrations')->upsert(
            [
                'member_id'   => $member->id,
                'provider'    => $provider,
                'external_id' => $externalId,
                'meta'        => json_encode($meta),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            ['provider', 'external_id'],
            ['member_id', 'meta', 'updated_at'],
        );
    }

    /**
     * Mark a member as opted out of WhatsApp messages.
     * Call this on unsubscribe webhook or manual opt-out request.
     *
     * @param string $phone
     */
    public function optOut(string $phone): void
    {
        Member::where('phone', $phone)->update(['whatsapp_opt_in' => false]);
    }

    /**
     * Normalise a phone number to digits-only with country code.
     * Strips spaces, dashes, parentheses. Prepends '91' if no country code detected.
     *
     * @param  string $raw Raw phone as entered by user
     * @return string      Normalised phone
     */
    public function normalise(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        // If 10 digits and starts with 6–9, assume Indian mobile — prepend 91
        if (strlen($digits) === 10 && in_array($digits[0], ['6', '7', '8', '9'], true)) {
            $digits = '91' . $digits;
        }

        return $digits;
    }
}
