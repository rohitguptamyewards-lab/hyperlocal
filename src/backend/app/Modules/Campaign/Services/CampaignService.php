<?php

namespace App\Modules\Campaign\Services;

use App\Modules\Campaign\Constants\CampaignTemplate;
use App\Modules\Campaign\Jobs\DispatchCampaignSends;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Campaign\Models\CampaignSend;
use App\Modules\Member\Models\Member;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages campaign lifecycle: create, schedule, run, cancel.
 * Segment resolution and individual send dispatch are handled by DispatchCampaignSends job.
 *
 * Owner module: Campaign
 * Tables owned: campaigns, campaign_sends
 * Reads but does not write: members
 * Fires: DispatchCampaignSends job
 */
class CampaignService
{
    /**
     * Create a campaign in DRAFT status.
     *
     * @param  int    $merchantId
     * @param  string $name
     * @param  string $templateKey    Must be in CampaignTemplate::VALID_KEYS
     * @param  array  $templateVars   Key-value pairs for template placeholders
     * @param  array  $targetSegment  Filter rules for member selection
     * @param  int    $createdBy      User ID
     * @return Campaign
     * @throws ValidationException
     */
    public function create(
        int    $merchantId,
        string $name,
        string $templateKey,
        array  $templateVars,
        array  $targetSegment,
        int    $createdBy,
    ): Campaign {
        $this->validateTemplate($templateKey, $templateVars);

        // Guard: if targeting partner segment, partnership must be LIVE
        // and the partner must have campaigns_enabled = true
        if (($targetSegment['source'] ?? 'own') === 'partner') {
            $uuid = $targetSegment['partnership_id'] ?? null;
            if (!$uuid) {
                throw ValidationException::withMessages([
                    'target_segment' => ['A partnership must be selected when targeting partner\'s customers.'],
                ]);
            }
            $this->assertPartnerCampaignAllowed($merchantId, $uuid);
        }

        return Campaign::create([
            'merchant_id'    => $merchantId,
            'name'           => $name,
            'template_key'   => $templateKey,
            'template_vars'  => $templateVars,
            'target_segment' => $targetSegment,
            'status'         => Campaign::STATUS_DRAFT,
            'created_by'     => $createdBy,
        ]);
    }

    /**
     * Schedule a draft campaign to run at a specific time.
     * Dispatches the send job to run at the scheduled time.
     *
     * @param  Campaign          $campaign
     * @param  \DateTimeInterface $scheduledAt
     * @throws ValidationException If campaign is not in DRAFT status
     */
    public function schedule(Campaign $campaign, \DateTimeInterface $scheduledAt): void
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => ['Only draft campaigns can be scheduled.']]);
        }

        $campaign->update([
            'status'       => Campaign::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);

        DispatchCampaignSends::dispatch($campaign->id)
            ->delay($scheduledAt)
            ->onQueue('campaigns');
    }

    /**
     * Run a draft campaign immediately.
     *
     * @param  Campaign $campaign
     * @throws ValidationException If campaign is not in DRAFT status
     */
    public function runNow(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => ['Only draft campaigns can be run.']]);
        }

        $campaign->update([
            'status'       => Campaign::STATUS_RUNNING,
            'started_at'   => now(),
            'scheduled_at' => now(),
        ]);

        DispatchCampaignSends::dispatch($campaign->id)->onQueue('campaigns');
    }

    /**
     * Cancel a scheduled or draft campaign.
     *
     * @param  Campaign $campaign
     * @throws ValidationException If campaign is already running or completed
     */
    public function cancel(Campaign $campaign): void
    {
        if (in_array($campaign->status, [Campaign::STATUS_RUNNING, Campaign::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['status' => ['Cannot cancel a campaign that is running or completed.']]);
        }

        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);
    }

    /**
     * Build the member query for a campaign's target segment.
     * Returns a Builder — callers must use chunkById() or count(), never ->get(),
     * to avoid loading an unbounded member list into memory.
     *
     * Supported filters:
     *   source         (string) — 'own' (default) | 'partner'
     *   partnership_id (string) — UUID of partner partnership (required when source=partner)
     *   last_seen_days (int)    — members seen within N days (own segment only)
     *   opt_in_only            — only whatsapp_opt_in = true (always applied)
     *
     * When source=partner:
     *   partner_filter = 'via_partnership' (default)
     *     Only members who received/redeemed a token from THIS specific partnership.
     *   partner_filter = 'all_customers'
     *     All members who are customers of the partner brand (any visit, not just this partnership).
     *
     * @param  Campaign $campaign
     * @return \Illuminate\Database\Eloquent\Builder<Member>
     */
    public function resolveSegment(Campaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        $query = Member::where('whatsapp_opt_in', true); // always filter opt-in

        $segment = $campaign->target_segment ?? [];

        $source = $segment['source'] ?? 'own';

        if ($source === 'partner' && !empty($segment['partnership_id'])) {
            // Guard at dispatch time: partnership still live + partner allows campaigns.
            // Returns empty set gracefully so a stuck campaign doesn't crash the job.
            if (!$this->isPartnerCampaignAllowed($campaign->merchant_id, $segment['partnership_id'])) {
                return $query->whereRaw('1 = 0');
            }

            $partnerFilter = $segment['partner_filter'] ?? 'via_partnership';

            if ($partnerFilter === 'all_customers') {
                // All customers of the partner brand —
                // any member who has a claim at the partner merchant (regardless of which partnership).
                $partnerMerchantId = DB::table('partnership_participants as pp')
                    ->join('partnerships as p', 'p.id', '=', 'pp.partnership_id')
                    ->where('p.uuid', $segment['partnership_id'])
                    ->where('pp.merchant_id', '!=', $campaign->merchant_id)
                    ->whereNull('pp.deleted_at')
                    ->value('pp.merchant_id');

                if (!$partnerMerchantId) {
                    return $query->whereRaw('1 = 0');
                }

                $query->whereExists(function ($sub) use ($partnerMerchantId): void {
                    $sub->select(DB::raw(1))
                        ->from('partner_claims')
                        ->whereColumn('partner_claims.member_id', 'members.id')
                        ->where('partner_claims.merchant_id', $partnerMerchantId);
                });
            } else {
                // via_partnership (default): only members who received/redeemed a token
                // through this specific partnership at the partner merchant.
                $query->whereExists(function ($sub) use ($campaign, $segment): void {
                    $sub->select(DB::raw(1))
                        ->from('partner_claims')
                        ->join('partnerships', 'partnerships.id', '=', 'partner_claims.partnership_id')
                        ->whereColumn('partner_claims.member_id', 'members.id')
                        ->where('partnerships.uuid', $segment['partnership_id'])
                        ->where('partner_claims.merchant_id', '!=', $campaign->merchant_id);
                });
            }
        } else {
            // Own segment: members who have claims at this merchant
            if (!empty($segment['last_seen_days'])) {
                $query->where('last_seen_at', '>=', now()->subDays((int) $segment['last_seen_days']));
            }

            $query->whereExists(function ($sub) use ($campaign): void {
                $sub->select(DB::raw(1))
                    ->from('partner_claims')
                    ->whereColumn('partner_claims.member_id', 'members.id')
                    ->where('partner_claims.merchant_id', $campaign->merchant_id);
            });
        }

        return $query;
    }

    /**
     * Count the estimated audience for a segment without creating a campaign.
     * Used by the segment preview endpoint.
     *
     * @param  int   $merchantId
     * @param  array $targetSegment  Same structure as Campaign::target_segment
     * @return int   Estimated audience count
     */
    public function previewSegmentCount(int $merchantId, array $targetSegment): int
    {
        // If partner segment, validate live + campaigns_enabled before counting
        if (($targetSegment['source'] ?? 'own') === 'partner') {
            $uuid = $targetSegment['partnership_id'] ?? null;
            if (!$uuid || !$this->isPartnerCampaignAllowed($merchantId, $uuid)) {
                return 0;
            }
        }

        // Synthesise a transient campaign object — never persisted
        $dummy               = new Campaign();
        $dummy->merchant_id    = $merchantId;
        $dummy->target_segment = $targetSegment;

        return $this->resolveSegment($dummy)->count();
    }

    // ─────────────────────────────────────────────────────────

    /**
     * Check (without throwing) whether a merchant is allowed to target
     * the partner's customers for a given partnership UUID.
     * Conditions: partnership must be LIVE and the partner's participant
     * row must have campaigns_enabled = true.
     */
    private function isPartnerCampaignAllowed(int $merchantId, string $partnershipUuid): bool
    {
        return DB::table('partnerships as p')
            ->join('partnership_participants as pp', function ($j) use ($merchantId) {
                $j->on('pp.partnership_id', '=', 'p.id')
                  ->where('pp.merchant_id', '!=', $merchantId)
                  ->where('pp.campaigns_enabled', true)
                  ->whereNull('pp.deleted_at');
            })
            ->where('p.uuid', $partnershipUuid)
            ->where('p.status', PartnershipStatus::LIVE)
            ->whereNull('p.deleted_at')
            ->exists();
    }

    /**
     * Same as isPartnerCampaignAllowed but throws a ValidationException on failure.
     *
     * @throws ValidationException
     */
    private function assertPartnerCampaignAllowed(int $merchantId, string $partnershipUuid): void
    {
        if (!$this->isPartnerCampaignAllowed($merchantId, $partnershipUuid)) {
            throw ValidationException::withMessages([
                'target_segment' => [
                    'This partnership is not live or the partner brand has not enabled campaign targeting.',
                ],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTemplate(string $templateKey, array $templateVars): void
    {
        if (!in_array($templateKey, CampaignTemplate::VALID_KEYS, true)) {
            throw ValidationException::withMessages([
                'template_key' => ['Invalid template key. Must be one of: ' . implode(', ', CampaignTemplate::VALID_KEYS)],
            ]);
        }

        $required = CampaignTemplate::requiredVars($templateKey);
        $missing  = array_diff($required, array_keys($templateVars));

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'template_vars' => ['Missing required template variables: ' . implode(', ', $missing)],
            ]);
        }
    }
}
