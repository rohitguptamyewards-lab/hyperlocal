<?php

namespace App\Modules\RulesEngine\Constants;

class RulesDenyReason
{
    public const TOKEN_INVALID        = 'TOKEN_INVALID';
    public const TOKEN_EXPIRED        = 'TOKEN_EXPIRED';
    public const TOKEN_ALREADY_USED   = 'TOKEN_ALREADY_USED';
    public const PARTNERSHIP_NOT_LIVE = 'PARTNERSHIP_NOT_LIVE';
    public const OUTLET_NOT_IN_SCOPE  = 'OUTLET_NOT_IN_SCOPE';
    public const MIN_BILL_NOT_MET     = 'MIN_BILL_NOT_MET';
    public const MONTHLY_CAP_REACHED  = 'MONTHLY_CAP_REACHED';
    public const PARTNER_CAP_REACHED  = 'PARTNER_CAP_REACHED';
    public const OUTLET_CAP_REACHED   = 'OUTLET_CAP_REACHED';
    public const BLACKOUT_DATE        = 'BLACKOUT_DATE';
    public const OUTSIDE_TIME_BAND    = 'OUTSIDE_TIME_BAND';
    public const USES_LIMIT_REACHED   = 'USES_LIMIT_REACHED';
    public const COOLING_PERIOD_ACTIVE = 'COOLING_PERIOD_ACTIVE';
    public const FIRST_TIME_ONLY      = 'FIRST_TIME_ONLY';
    public const STACKING_BLOCKED     = 'STACKING_BLOCKED';
    public const DAILY_CAP_REACHED        = 'DAILY_CAP_REACHED';
    public const OUTLET_DAILY_CAP_REACHED = 'OUTLET_DAILY_CAP_REACHED';
    public const LIFETIME_CAP_REACHED     = 'LIFETIME_CAP_REACHED';
    public const ECOSYSTEM_INACTIVE       = 'ECOSYSTEM_INACTIVE'; // E-001

    /** Human-readable messages shown on the cashier screen */
    public const DISPLAY = [
        self::TOKEN_INVALID        => 'This claim code is not valid.',
        self::TOKEN_EXPIRED        => 'This claim code has expired. Ask the customer to generate a new one.',
        self::TOKEN_ALREADY_USED   => 'This claim code has already been redeemed.',
        self::PARTNERSHIP_NOT_LIVE => 'This partnership is not currently active.',
        self::OUTLET_NOT_IN_SCOPE  => 'This outlet is not part of this partnership.',
        self::MIN_BILL_NOT_MET     => 'The bill amount is below the minimum required for this offer.',
        self::MONTHLY_CAP_REACHED  => 'Monthly partnership limit has been reached.',
        self::PARTNER_CAP_REACHED  => 'Partner monthly limit has been reached.',
        self::OUTLET_CAP_REACHED   => 'This outlet\'s monthly limit has been reached.',
        self::BLACKOUT_DATE        => 'This offer is not valid today.',
        self::OUTSIDE_TIME_BAND    => 'This offer is only valid during specific hours.',
        self::USES_LIMIT_REACHED   => 'This customer has reached the maximum uses for this offer.',
        self::COOLING_PERIOD_ACTIVE => 'This customer used this offer recently. Please wait before using again.',
        self::FIRST_TIME_ONLY      => 'This offer is for first-time customers only.',
        self::STACKING_BLOCKED     => 'This offer cannot be combined with another active discount.',
        self::DAILY_CAP_REACHED        => 'Today\'s partnership benefit limit has been reached. Please try again tomorrow.',
        self::OUTLET_DAILY_CAP_REACHED => 'This outlet\'s daily limit has been reached. Please try another outlet or visit tomorrow.',
        self::LIFETIME_CAP_REACHED     => 'The total limit for this partnership has been reached.',
        self::ECOSYSTEM_INACTIVE       => 'This partnership is no longer active — one of the merchants is no longer in the network.',
    ];

    public static function display(string $code): string
    {
        return self::DISPLAY[$code] ?? 'This offer cannot be applied. Contact your outlet manager.';
    }
}
