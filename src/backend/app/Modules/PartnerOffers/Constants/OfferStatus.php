<?php

namespace App\Modules\PartnerOffers\Constants;

class OfferStatus
{
    public const ACTIVE = 1;
    public const INACTIVE = 2;

    public const LABELS = [
        self::ACTIVE   => 'Active',
        self::INACTIVE => 'Inactive',
    ];
}
