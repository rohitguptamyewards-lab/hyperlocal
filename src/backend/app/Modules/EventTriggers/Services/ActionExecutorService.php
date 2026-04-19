<?php

namespace App\Modules\EventTriggers\Services;

use App\Modules\EventTriggers\Constants\TriggerActionType;
use App\Modules\EventTriggers\Models\EventTrigger;
use App\Modules\Member\Models\Member;
use App\Modules\PartnerOffers\Models\PartnerOffer;
use Illuminate\Support\Facades\Log;

/**
 * Executes actions determined by the TriggerEngine.
 * Each action type has its own handler method.
 */
class ActionExecutorService
{
    /**
     * @return array Outcome summary
     */
    public function execute(EventTrigger $trigger, array $payload, ?Member $member): array
    {
        return match ($trigger->action_type) {
            TriggerActionType::ISSUE_OFFER   => $this->issueOffer($trigger, $payload, $member),
            TriggerActionType::MAKE_ELIGIBLE => $this->makeEligible($trigger, $payload, $member),
            TriggerActionType::SEND_WHATSAPP => $this->sendWhatsApp($trigger, $payload, $member),
            TriggerActionType::SEND_CAMPAIGN => $this->sendCampaign($trigger, $payload, $member),
            default => ['action' => 'unknown', 'status' => 'skipped'],
        };
    }

    private function issueOffer(EventTrigger $trigger, array $payload, ?Member $member): array
    {
        $offerId = $trigger->offer_id ?? ($trigger->action_config_json['offer_id'] ?? null);
        if (!$offerId) {
            return ['action' => 'issue_offer', 'status' => 'failed', 'reason' => 'no offer_id configured'];
        }

        $offer = PartnerOffer::find($offerId);
        if (!$offer || $offer->status !== 1) {
            return ['action' => 'issue_offer', 'status' => 'failed', 'reason' => 'offer not found or inactive'];
        }

        // For now: log the issuance. In production this would create an eligibility record
        // or directly attach the offer to the customer's rewards page.
        Log::info('EventTrigger: offer issued', [
            'offer_id'    => $offerId,
            'offer_title' => $offer->title,
            'member_id'   => $member?->id,
            'merchant_id' => $trigger->merchant_id,
            'event_type'  => $payload['event_type'] ?? 'unknown',
        ]);

        return [
            'action'      => 'issue_offer',
            'status'      => 'completed',
            'offer_id'    => $offerId,
            'offer_title' => $offer->title,
            'member_id'   => $member?->id,
        ];
    }

    private function makeEligible(EventTrigger $trigger, array $payload, ?Member $member): array
    {
        Log::info('EventTrigger: customer made eligible', [
            'trigger_id'  => $trigger->id,
            'member_id'   => $member?->id,
            'merchant_id' => $trigger->merchant_id,
        ]);

        return ['action' => 'make_eligible', 'status' => 'completed', 'member_id' => $member?->id];
    }

    private function sendWhatsApp(EventTrigger $trigger, array $payload, ?Member $member): array
    {
        // Placeholder — would call WhatsAppNotifier
        Log::info('EventTrigger: WhatsApp send queued', [
            'member_id' => $member?->id,
            'phone'     => $member?->phone,
        ]);

        return ['action' => 'send_whatsapp', 'status' => 'queued', 'member_id' => $member?->id];
    }

    private function sendCampaign(EventTrigger $trigger, array $payload, ?Member $member): array
    {
        Log::info('EventTrigger: campaign send queued', [
            'trigger_id' => $trigger->id,
            'member_id'  => $member?->id,
        ]);

        return ['action' => 'send_campaign', 'status' => 'queued', 'member_id' => $member?->id];
    }
}
