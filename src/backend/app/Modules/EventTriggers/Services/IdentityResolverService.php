<?php

namespace App\Modules\EventTriggers\Services;

use App\Modules\Member\Models\Member;
use App\Modules\Member\Services\MemberService;

/**
 * Resolves a customer reference from an incoming event to an internal Member.
 * Priority: member_id → phone → email → external_id → create new.
 */
class IdentityResolverService
{
    public function __construct(private readonly MemberService $members) {}

    public function resolve(array $customerRef): ?Member
    {
        if (!empty($customerRef['member_id'])) {
            return Member::find($customerRef['member_id']);
        }

        if (!empty($customerRef['phone'])) {
            $phone = $this->members->normalise($customerRef['phone']);
            return $this->members->findOrCreateByPhone($phone);
        }

        if (!empty($customerRef['email'])) {
            $member = Member::where('email', $customerRef['email'])->first();
            if ($member) return $member;
        }

        if (!empty($customerRef['external_id'])) {
            $row = \DB::table('member_integrations')
                ->where('external_id', $customerRef['external_id'])
                ->first();
            if ($row) return Member::find($row->member_id);
        }

        // Create new member from whatever we have
        if (!empty($customerRef['phone'])) {
            return $this->members->findOrCreateByPhone(
                $this->members->normalise($customerRef['phone'])
            );
        }

        return null;
    }
}
