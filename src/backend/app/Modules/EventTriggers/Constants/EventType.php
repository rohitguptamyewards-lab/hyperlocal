<?php

namespace App\Modules\EventTriggers\Constants;

class EventType
{
    public const TRANSACTION_COMPLETED = 'transaction_completed';
    public const FIRST_PURCHASE        = 'first_purchase';
    public const ORDER_ABOVE_THRESHOLD = 'order_above_threshold';
    public const BOOKING_CONFIRMED     = 'booking_confirmed';
    public const ORDER_DELIVERED       = 'order_delivered';
    public const MEMBERSHIP_ACTIVATED  = 'membership_activated';
    public const MILESTONE_REACHED     = 'milestone_reached';
    public const CATEGORY_PURCHASED    = 'category_purchased';

    public const VALID = [
        self::TRANSACTION_COMPLETED, self::FIRST_PURCHASE,
        self::ORDER_ABOVE_THRESHOLD, self::BOOKING_CONFIRMED,
        self::ORDER_DELIVERED,       self::MEMBERSHIP_ACTIVATED,
        self::MILESTONE_REACHED,     self::CATEGORY_PURCHASED,
    ];

    public const LABELS = [
        self::TRANSACTION_COMPLETED => 'Transaction completed',
        self::FIRST_PURCHASE        => 'First purchase',
        self::ORDER_ABOVE_THRESHOLD => 'Order above threshold',
        self::BOOKING_CONFIRMED     => 'Booking confirmed',
        self::ORDER_DELIVERED       => 'Order delivered',
        self::MEMBERSHIP_ACTIVATED  => 'Membership activated',
        self::MILESTONE_REACHED     => 'Milestone reached',
        self::CATEGORY_PURCHASED    => 'Category purchased',
    ];
}
