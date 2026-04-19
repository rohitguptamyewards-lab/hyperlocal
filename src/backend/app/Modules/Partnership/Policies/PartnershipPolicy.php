<?php

namespace App\Modules\Partnership\Policies;

use App\Models\User;
use App\Modules\Partnership\Models\Partnership;

class PartnershipPolicy
{
    /** Any authenticated user of this merchant can view */
    public function view(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership);
    }

    /** Only admin/manager can create */
    public function create(User $user): bool
    {
        return in_array($user->role, [1, 2], true);
    }

    /** Either participant's admin/manager can edit terms while the partnership is editable */
    public function update(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true)
            && $partnership->isEditable();
    }

    /** Acceptor side approves */
    public function accept(User $user, Partnership $partnership): bool
    {
        return $this->isAcceptorMember($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Acceptor can accept and immediately go live in one step */
    public function acceptAndGoLive(User $user, Partnership $partnership): bool
    {
        return $this->isAcceptorMember($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Either party's admin can transition AGREED → LIVE */
    public function goLive(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Either side's admin can reject */
    public function reject(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Either side's admin can pause a LIVE partnership */
    public function pause(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Either side's admin can resume */
    public function resume(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Any participant's admin/manager can update their own side's permission flags */
    public function updateMySettings(User $user, Partnership $partnership): bool
    {
        return $this->isMemberOf($user, $partnership)
            && in_array($user->role, [1, 2], true);
    }

    /** Keep old names as aliases for backward-compat in case any route still calls them */
    public function suspendMySide(User $user, Partnership $partnership): bool
    {
        return $this->updateMySettings($user, $partnership);
    }

    public function unsuspendMySide(User $user, Partnership $partnership): bool
    {
        return $this->updateMySettings($user, $partnership);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isMemberOf(User $user, Partnership $partnership): bool
    {
        return $partnership->participants()
            ->where('merchant_id', $user->merchant_id)
            ->exists();
    }

    private function isProposerMember(User $user, Partnership $partnership): bool
    {
        return $partnership->merchant_id === $user->merchant_id;
    }

    private function isAcceptorMember(User $user, Partnership $partnership): bool
    {
        return $partnership->participants()
            ->where('merchant_id', $user->merchant_id)
            ->where('role', 2)
            ->exists();
    }
}
