<?php

namespace App\Modules\Enablement\Services;

use App\Events\PartnershipDormant;
use App\Modules\Enablement\Models\StaffEnablement;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Facades\DB;

/**
 * DormancyService — detects and flags dormant partnership outlets.
 *
 * Owner module: Enablement
 * Reads:   partnership_staff_enablement, partnerships
 * Writes:  partnership_staff_enablement (is_dormant, dormant_since, dormancy_alert_sent)
 * Fires:   PartnershipDormant (once per dormancy cycle, guarded by dormancy_alert_sent)
 *
 * Dormancy logic:
 *   An outlet is dormant when:
 *     COALESCE(last_used_at, created_at) < now() - DORMANCY_THRESHOLD_DAYS
 *   Recovery: a new redemption (handled by UpdateLastUsedAtOnRedemption) resets the flags.
 */
class DormancyService
{
    public const DORMANCY_THRESHOLD_DAYS = 14;

    /**
     * Run dormancy detection across all LIVE partnerships.
     *
     * @return array{checked: int, newly_dormant: int, alerts_sent: int, recovered: int}
     */
    public function checkAll(): array
    {
        $stats = ['checked' => 0, 'newly_dormant' => 0, 'alerts_sent' => 0, 'recovered' => 0];

        // Only check enablement rows that belong to LIVE partnerships
        $rows = StaffEnablement::whereHas('partnership', function ($q) {
            $q->where('status', PartnershipStatus::LIVE);
        })->get();

        $threshold = now()->subDays(self::DORMANCY_THRESHOLD_DAYS);

        foreach ($rows as $row) {
            $stats['checked']++;

            // Last activity = last_used_at if set, otherwise row creation date
            $lastActivity = $row->last_used_at ?? $row->created_at;

            if ($lastActivity->lt($threshold)) {
                // Outlet should be dormant
                if (!$row->is_dormant) {
                    $row->update([
                        'is_dormant'    => true,
                        'dormant_since' => now(),
                    ]);
                    $stats['newly_dormant']++;
                }

                // Fire alert once per cycle (dormancy_alert_sent resets on recovery)
                if (!$row->dormancy_alert_sent) {
                    PartnershipDormant::dispatch(
                        $row->id,
                        $row->partnership_id,
                        $row->merchant_id,
                        $row->outlet_id,
                    );
                    $row->update(['dormancy_alert_sent' => true]);
                    $stats['alerts_sent']++;
                }
            } else {
                // Outlet is active — recover if it was flagged
                // Note: recovery is also handled immediately by UpdateLastUsedAtOnRedemption.
                // This path catches any rows that recovered but whose listener may have been missed.
                if ($row->is_dormant) {
                    $row->update([
                        'is_dormant'          => false,
                        'dormant_since'       => null,
                        'dormancy_alert_sent' => false,
                    ]);
                    $stats['recovered']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Get dormancy summary for a specific merchant across all their LIVE partnerships.
     *
     * @return array{total_outlets: int, dormant_outlets: int, dormancy_rate: float|null}
     */
    public function summaryForMerchant(int $merchantId): array
    {
        $rows = StaffEnablement::forMerchant($merchantId)
            ->whereHas('partnership', fn ($q) => $q->where('status', PartnershipStatus::LIVE))
            ->get();

        $total   = $rows->count();
        $dormant = $rows->where('is_dormant', true)->count();

        return [
            'total_outlets'  => $total,
            'dormant_outlets' => $dormant,
            'dormancy_rate'  => $total > 0 ? round($dormant / $total * 100, 1) : null,
        ];
    }
}
