<?php

namespace App\Modules\Partnership\Constants;

class ScopeType
{
    public const OUTLET = 1;
    public const BRAND  = 2;

    public const LABELS = [
        self::OUTLET => 'Outlet-level',
        self::BRAND  => 'Brand-wide',
    ];
}
