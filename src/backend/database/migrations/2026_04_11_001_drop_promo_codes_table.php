<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the promo_codes table.
 * Decision: PromoCode module removed entirely (Session 25).
 * External POS owns the coupon lifecycle — no local registry needed for V1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('promo_codes');
    }

    public function down(): void
    {
        // Intentionally empty — table can be re-created if PromoCode module is built later.
    }
};
