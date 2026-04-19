<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a merchant's ecosystem_active flag is set to false.
 *
 * Owner module: Partnership (listener auto-closes partnerships)
 * Fired by: Admin\Http\Controllers\EcosystemController::deactivate()
 *           (eWards migration: fired by webhook handler instead)
 *
 * E-001 LOCKED 2026-04-10
 */
class MerchantEcosystemExit
{
    use Dispatchable;

    public function __construct(
        public readonly int    $merchantId,
        public readonly string $reason,
    ) {}
}
