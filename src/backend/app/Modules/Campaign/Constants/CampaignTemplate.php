<?php

namespace App\Modules\Campaign\Constants;

/**
 * Fixed WhatsApp template keys — V1 only.
 * No custom message bodies. Merchants fill in VARIABLES only.
 *
 * Each key maps to a pre-approved Meta WhatsApp Business template.
 * Adding a new key requires Meta template approval BEFORE deploying.
 *
 * Variables documented per template — all are required unless marked (optional).
 */
final class CampaignTemplate
{
    /** Member earned points via a partnership referral */
    public const PARTNERSHIP_EARN = 'partnership_earn';
    // Variables: member_name, points, issuer_merchant, partner_merchant

    /** Points expiring soon — scheduled reminder */
    public const POINTS_EXPIRY_REMINDER = 'points_expiry_reminder';
    // Variables: member_name, points, merchant, expiry_date

    /** Redemption confirmed */
    public const REDEMPTION_CONFIRMATION = 'redemption_confirmation';
    // Variables: member_name, value, merchant

    /** First earn in a new partnership — welcome message */
    public const PARTNERSHIP_WELCOME = 'partnership_welcome';
    // Variables: member_name, brand_a, brand_b

    /** Generic offer announcement — replaces old coupon_issued template */
    public const OFFER_ANNOUNCEMENT = 'offer_announcement';
    // Variables: member_name, offer_details, merchant

    /** All valid template keys — validated on campaign create */
    public const VALID_KEYS = [
        self::PARTNERSHIP_EARN,
        self::POINTS_EXPIRY_REMINDER,
        self::REDEMPTION_CONFIRMATION,
        self::PARTNERSHIP_WELCOME,
        self::OFFER_ANNOUNCEMENT,
    ];

    /** Human-readable label for UI display */
    public static function label(string $key): string
    {
        return match ($key) {
            self::PARTNERSHIP_EARN        => 'Partnership earn notification',
            self::POINTS_EXPIRY_REMINDER  => 'Points expiry reminder',
            self::REDEMPTION_CONFIRMATION => 'Redemption confirmation',
            self::PARTNERSHIP_WELCOME     => 'Partnership welcome',
            self::OFFER_ANNOUNCEMENT     => 'Offer announcement',
            default                       => $key,
        };
    }

    /**
     * Whether this template is semantically suited to a partner segment.
     * UI uses this to pre-select "partner's customers" as the default segment source.
     */
    public static function suggestsPartnerSegment(string $key): bool
    {
        return in_array($key, [
            self::PARTNERSHIP_WELCOME,
            self::PARTNERSHIP_EARN,
        ], true);
    }

    /** Required variable names per template — used for request validation */
    public static function requiredVars(string $key): array
    {
        return match ($key) {
            self::PARTNERSHIP_EARN        => ['member_name', 'points', 'issuer_merchant', 'partner_merchant'],
            self::POINTS_EXPIRY_REMINDER  => ['member_name', 'points', 'merchant', 'expiry_date'],
            self::REDEMPTION_CONFIRMATION => ['member_name', 'value', 'merchant'],
            self::PARTNERSHIP_WELCOME     => ['member_name', 'brand_a', 'brand_b'],
            self::OFFER_ANNOUNCEMENT     => ['member_name', 'offer_details', 'merchant'],
            default                       => [],
        };
    }
}
