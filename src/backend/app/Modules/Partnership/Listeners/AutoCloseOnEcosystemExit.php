<?php

namespace App\Modules\Partnership\Listeners;

use App\Events\MerchantEcosystemExit;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Facades\DB;

/**
 * Listens to: MerchantEcosystemExit
 *
 * When a merchant leaves the eWards ecosystem, all their LIVE and PAUSED
 * partnerships are moved to ECOSYSTEM_INACTIVE (status=9).
 *
 * Logic:
 *   - Find all partnerships where this merchant is a participant AND
 *     status is any active state (SUGGESTED through PAUSED).
 *   - Set status → ECOSYSTEM_INACTIVE, record reason + timestamp.
 *   - Chunked in batches of 500 to handle merchants with many partnerships.
 *
 * Owner module: Partnership
 * Reads: partnership_participants, partnerships
 * Writes: partnerships (status + ecosystem_exit_at + ecosystem_exit_reason)
 * DO NOT write to partner_redemptions or partner_claims from here.
 *
 * E-001 LOCKED 2026-04-10
 */
class AutoCloseOnEcosystemExit
{
    private const CHUNK_SIZE = 500;

    public function handle(MerchantEcosystemExit $event): void
    {
        // Find all partnership IDs where this merchant is a participant
        // and the partnership is still active
        DB::table('partnership_participants')
            ->where('merchant_id', $event->merchantId)
            ->whereNull('deleted_at')
            ->select('partnership_id')
            ->orderBy('partnership_id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($event) {
                $partnershipIds = $rows->pluck('partnership_id')->toArray();

                DB::table('partnerships')
                    ->whereIn('id', $partnershipIds)
                    ->whereIn('status', [
                        PartnershipStatus::SUGGESTED,
                        PartnershipStatus::REQUESTED,
                        PartnershipStatus::NEGOTIATING,
                        PartnershipStatus::AGREED,
                        PartnershipStatus::LIVE,
                        PartnershipStatus::PAUSED,
                    ])
                    ->update([
                        'status'                  => PartnershipStatus::ECOSYSTEM_INACTIVE,
                        'ecosystem_exit_reason'   => $event->reason,
                        'ecosystem_exit_at'       => now(),
                        'updated_at'              => now(),
                    ]);
            });
    }
}
