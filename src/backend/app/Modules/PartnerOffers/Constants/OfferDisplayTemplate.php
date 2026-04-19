<?php

namespace App\Modules\PartnerOffers\Constants;

class OfferDisplayTemplate
{
    public const SIMPLE = 'simple';
    public const SCRATCH = 'scratch';
    public const CAROUSEL = 'carousel';

    public const VALID_KEYS = [
        self::SIMPLE,
        self::SCRATCH,
        self::CAROUSEL,
    ];

    public const LABELS = [
        self::SIMPLE   => 'Simple card list',
        self::SCRATCH  => 'Scratch to reveal',
        self::CAROUSEL => 'Swipeable carousel',
    ];
}
