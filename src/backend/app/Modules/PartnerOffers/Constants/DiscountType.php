<?php

namespace App\Modules\PartnerOffers\Constants;

class DiscountType
{
    public const PERCENTAGE = 1;
    public const FLAT = 2;

    public const LABELS = [
        self::PERCENTAGE => 'Percentage',
        self::FLAT       => 'Flat amount',
    ];
}
