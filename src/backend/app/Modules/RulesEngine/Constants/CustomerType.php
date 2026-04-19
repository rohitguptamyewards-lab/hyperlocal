<?php

namespace App\Modules\RulesEngine\Constants;

class CustomerType
{
    public const NEW         = 1;
    public const EXISTING    = 2;
    public const REACTIVATED = 3;

    public const LABELS = [
        self::NEW         => 'New',
        self::EXISTING    => 'Existing',
        self::REACTIVATED => 'Reactivated',
    ];

    public static function label(int $type): string
    {
        return self::LABELS[$type] ?? 'Unknown';
    }
}
