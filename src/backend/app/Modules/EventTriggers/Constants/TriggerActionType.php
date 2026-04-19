<?php

namespace App\Modules\EventTriggers\Constants;

class TriggerActionType
{
    public const ISSUE_OFFER    = 'issue_offer';
    public const SEND_WHATSAPP  = 'send_whatsapp';
    public const MAKE_ELIGIBLE  = 'make_eligible';
    public const SEND_CAMPAIGN  = 'send_campaign';

    public const VALID = [self::ISSUE_OFFER, self::SEND_WHATSAPP, self::MAKE_ELIGIBLE, self::SEND_CAMPAIGN];

    public const LABELS = [
        self::ISSUE_OFFER   => 'Issue partner offer',
        self::SEND_WHATSAPP => 'Send WhatsApp message',
        self::MAKE_ELIGIBLE => 'Make customer eligible',
        self::SEND_CAMPAIGN => 'Trigger campaign send',
    ];
}
