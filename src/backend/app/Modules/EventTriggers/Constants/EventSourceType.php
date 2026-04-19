<?php

namespace App\Modules\EventTriggers\Constants;

class EventSourceType
{
    public const WEBSITE     = 'website';
    public const API         = 'api';
    public const SHOPIFY     = 'shopify';
    public const WOOCOMMERCE = 'woocommerce';
    public const EWRDS       = 'ewrds';

    public const VALID = [self::WEBSITE, self::API, self::SHOPIFY, self::WOOCOMMERCE, self::EWRDS];

    public const LABELS = [
        self::WEBSITE     => 'Website / Thank-you page',
        self::API         => 'Server-to-server API',
        self::SHOPIFY     => 'Shopify',
        self::WOOCOMMERCE => 'WooCommerce',
        self::EWRDS       => 'eWards POS',
    ];
}
