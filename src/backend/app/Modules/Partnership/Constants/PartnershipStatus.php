<?php

namespace App\Modules\Partnership\Constants;

class PartnershipStatus
{
    public const SUGGESTED          = 1;
    public const REQUESTED          = 2;
    public const NEGOTIATING        = 3;
    public const AGREED             = 4;
    public const LIVE               = 5;
    public const PAUSED             = 6;
    public const EXPIRED            = 7;
    public const REJECTED           = 8;
    /** E-001: one or both merchants left the eWards ecosystem. Non-actionable. */
    public const ECOSYSTEM_INACTIVE = 9;

    public const LABELS = [
        self::SUGGESTED          => 'Suggested',
        self::REQUESTED          => 'Requested',
        self::NEGOTIATING        => 'Negotiating',
        self::AGREED             => 'Agreed',
        self::LIVE               => 'Live',
        self::PAUSED             => 'Paused',
        self::EXPIRED            => 'Expired',
        self::REJECTED           => 'Rejected',
        self::ECOSYSTEM_INACTIVE => 'Ecosystem inactive',
    ];

    /** Statuses where terms/rules can still be edited */
    public const EDITABLE = [self::SUGGESTED, self::REQUESTED, self::NEGOTIATING, self::AGREED];

    /** Valid transitions: from → allowed next statuses */
    public const TRANSITIONS = [
        self::SUGGESTED          => [self::REQUESTED, self::REJECTED, self::ECOSYSTEM_INACTIVE],
        self::REQUESTED          => [self::NEGOTIATING, self::AGREED, self::LIVE, self::REJECTED, self::ECOSYSTEM_INACTIVE],
        self::NEGOTIATING        => [self::AGREED, self::LIVE, self::REJECTED, self::ECOSYSTEM_INACTIVE],
        self::AGREED             => [self::LIVE, self::ECOSYSTEM_INACTIVE],
        self::LIVE               => [self::PAUSED, self::EXPIRED, self::ECOSYSTEM_INACTIVE],
        self::PAUSED             => [self::LIVE, self::EXPIRED, self::ECOSYSTEM_INACTIVE],
        self::EXPIRED            => [],
        self::REJECTED           => [],
        self::ECOSYSTEM_INACTIVE => [],  // terminal — re-activation handled by eWards webhook
    ];

    public static function canTransition(int $from, int $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'Unknown';
    }
}
