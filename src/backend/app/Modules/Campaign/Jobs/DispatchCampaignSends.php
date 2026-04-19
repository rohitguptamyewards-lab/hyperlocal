<?php

namespace App\Modules\Campaign\Jobs;

use App\Modules\Campaign\Models\Campaign;
use App\Modules\Campaign\Models\CampaignSend;
use App\Modules\Campaign\Services\CampaignService;
use App\Modules\CustomerActivation\Services\WhatsAppNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the member segment, creates campaign_sends rows, and dispatches
 * individual WhatsApp messages via WhatsAppNotifier.
 *
 * Chunked: processes 200 members at a time to avoid memory exhaustion.
 * Idempotent: campaign_sends unique constraint prevents double-sends on retry.
 *
 * Queue: 'campaigns' — separate from 'default' to avoid blocking redemption flow.
 *
 * Owner module: Campaign
 * Reads: members (via CampaignService::resolveSegment)
 * Writes: campaigns (status), campaign_sends
 */
class DispatchCampaignSends implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 200;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        private readonly int $campaignId,
    ) {}

    public function handle(CampaignService $campaignService, \App\Modules\CustomerActivation\Services\WhatsAppNotifier $whatsapp): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign || $campaign->status === Campaign::STATUS_CANCELLED) {
            return;
        }

        // Mark running (idempotent — already running if retried)
        $campaign->update([
            'status'     => Campaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $segmentQuery = $campaignService->resolveSegment($campaign);
        $total        = $segmentQuery->count();
        $sent         = 0;
        $failed       = 0;

        // Upsert send rows in bulk — chunked to avoid memory exhaustion (idempotent)
        $campaignService->resolveSegment($campaign)
            ->chunkById(self::CHUNK_SIZE, function ($chunk) use ($campaign): void {
                $rows = $chunk->map(fn ($m) => [
                    'campaign_id' => $campaign->id,
                    'member_id'   => $m->id,
                    'status'      => CampaignSend::STATUS_PENDING,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])->values()->all();

                DB::table('campaign_sends')->upsert(
                    $rows,
                    ['campaign_id', 'member_id'],
                    ['updated_at'],
                );
            });

        // Process pending sends
        CampaignSend::where('campaign_id', $campaign->id)
            ->where('status', CampaignSend::STATUS_PENDING)
            ->with('member')
            ->chunkById(self::CHUNK_SIZE, function ($sends) use ($campaign, &$sent, &$failed): void {
                foreach ($sends as $send) {
                    if (!$send->member->whatsapp_opt_in) {
                        $send->update(['status' => CampaignSend::STATUS_FAILED, 'error_message' => 'opted_out']);
                        $failed++;
                        continue;
                    }

                    try {
                        $message = $this->buildMessage($campaign, $send->member->name ?? $send->member->phone);

                        $whatsapp->sendRaw(
                            phone:       $send->member->phone,
                            message:     $message,
                            merchantId:  $campaign->merchant_id,
                            referenceId: $send->id,
                        );

                        $send->update([
                            'status'  => CampaignSend::STATUS_SENT,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    } catch (\App\Modules\WhatsAppCredit\Exceptions\InsufficientWhatsAppCreditsException $e) {
                        // Credits exhausted mid-campaign — mark all remaining sends failed and stop
                        CampaignSend::where('campaign_id', $campaign->id)
                            ->where('status', CampaignSend::STATUS_PENDING)
                            ->update([
                                'status'        => CampaignSend::STATUS_FAILED,
                                'error_message' => 'insufficient_credits',
                                'updated_at'    => now(),
                            ]);
                        $campaign->update(['status' => Campaign::STATUS_COMPLETED]);
                        Log::warning('Campaign aborted — insufficient WhatsApp credits.', [
                            'campaign_id' => $campaign->id,
                            'merchant_id' => $campaign->merchant_id,
                            'sent_so_far' => $sent,
                        ]);
                        return; // exit the chunkById callback
                    } catch (\Throwable $e) {
                        $send->update([
                            'status'        => CampaignSend::STATUS_FAILED,
                            'error_message' => substr($e->getMessage(), 0, 500),
                        ]);
                        $failed++;
                        Log::warning('CampaignSend failed', [
                            'campaign_id' => $campaign->id,
                            'member_id'   => $send->member_id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        $campaign->update([
            'status'       => Campaign::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Log::info('Campaign completed', [
            'campaign_id' => $campaign->id,
            'total'       => $total,
            'sent'        => $sent,
            'failed'      => $failed,
        ]);
    }

    private function buildMessage(Campaign $campaign, string $recipientName): string
    {
        $vars    = $campaign->template_vars ?? [];
        $vars['member_name'] = $recipientName;

        // Template message body — maps template_key to human-readable message
        // In production these will be Meta-approved templates sent via MSG91 template API
        $rewardsUrl = config('app.url') . '/my-rewards';

        $template = match ($campaign->template_key) {
            'partnership_earn'        => "Hi {member_name}, you earned {points} points at {issuer_merchant} thanks to your visit to {partner_merchant}! Check your rewards: {rewards_url}",
            'offer_announcement'      => "Hi {member_name}, {offer_details} at {merchant}. Don't miss out! See all your rewards: {rewards_url}",
            'points_expiry_reminder'  => "Hi {member_name}, your {points} points at {merchant} expire on {expiry_date}. Use them before they're gone! Check: {rewards_url}",
            'redemption_confirmation' => "Hi {member_name}, your benefit of {value} has been redeemed at {merchant}. Thank you! View rewards: {rewards_url}",
            'partnership_welcome'     => "Hi {member_name}, welcome to the {brand_a} x {brand_b} partnership. Visit either store to earn rewards! See your balance: {rewards_url}",
            default                   => "Hi {member_name}, you have a message from {merchant}. View rewards: {rewards_url}",
        };

        $vars['rewards_url'] = $rewardsUrl;

        foreach ($vars as $key => $value) {
            $template = str_replace("{{$key}}", (string) $value, $template);
        }

        return $template;
    }
}
