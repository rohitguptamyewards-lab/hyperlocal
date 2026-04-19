<?php

namespace App\Modules\Growth\Services;

use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referral links per partnership (#1), merchant invite system (#5),
 * referral revenue sharing (#15).
 */
class ReferralService
{
    /**
     * Generate or retrieve a referral link for a merchant's partnership.
     */
    public function getReferralLink(int $partnershipId, int $merchantId): array
    {
        $existing = DB::table('partnership_referral_links')
            ->where('partnership_id', $partnershipId)
            ->where('merchant_id', $merchantId)
            ->first();

        if ($existing) {
            return [
                'code'             => $existing->code,
                'url'              => config('app.url') . '/r/' . $existing->code,
                'click_count'      => $existing->click_count,
                'conversion_count' => $existing->conversion_count,
            ];
        }

        $code = strtoupper(Str::random(8));

        DB::table('partnership_referral_links')->insert([
            'partnership_id' => $partnershipId,
            'merchant_id'    => $merchantId,
            'code'           => $code,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return [
            'code'             => $code,
            'url'              => config('app.url') . '/r/' . $code,
            'click_count'      => 0,
            'conversion_count' => 0,
        ];
    }

    /**
     * Record a referral link click.
     */
    public function recordClick(string $code): ?array
    {
        $link = DB::table('partnership_referral_links')->where('code', $code)->first();
        if (!$link) return null;

        DB::table('partnership_referral_links')->where('id', $link->id)
            ->increment('click_count');

        return [
            'partnership_id' => $link->partnership_id,
            'merchant_id'    => $link->merchant_id,
        ];
    }

    /**
     * Create a merchant invite (WhatsApp share).
     */
    public function createInvite(int $inviterMerchantId, ?string $phone = null, ?string $email = null): array
    {
        $token = Str::random(32);

        DB::table('merchant_referral_invites')->insert([
            'inviter_merchant_id' => $inviterMerchantId,
            'invitee_phone'       => $phone,
            'invitee_email'       => $email,
            'invite_token'        => $token,
            'status'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $inviter = Merchant::find($inviterMerchantId);
        $url = config('app.url') . '/join/' . $token;

        return [
            'invite_token' => $token,
            'invite_url'   => $url,
            'whatsapp_text' => "Hey! I'm using Hyperlocal Network to get new customers from nearby brands. It's been great — {$inviter->name} got real results. Try it: {$url}",
        ];
    }

    /**
     * Mark invite as converted and award credits.
     */
    public function convertInvite(string $token, int $newMerchantId): void
    {
        $invite = DB::table('merchant_referral_invites')
            ->where('invite_token', $token)
            ->where('status', 1)
            ->first();

        if (!$invite) return;

        $creditsToAward = 50; // Bonus credits for successful referral

        DB::table('merchant_referral_invites')
            ->where('id', $invite->id)
            ->update([
                'status'          => 2,
                'credits_awarded' => $creditsToAward,
                'updated_at'      => now(),
            ]);
    }

    public function getInviteStats(int $merchantId): array
    {
        $total = DB::table('merchant_referral_invites')
            ->where('inviter_merchant_id', $merchantId)->count();
        $converted = DB::table('merchant_referral_invites')
            ->where('inviter_merchant_id', $merchantId)
            ->where('status', '>=', 2)->count();

        return ['total_invites' => $total, 'converted' => $converted];
    }
}
