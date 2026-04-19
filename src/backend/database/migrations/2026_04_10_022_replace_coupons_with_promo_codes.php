<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the old CouponEngine tables with a minimal promo-code registry.
 *
 * NEW MODEL:
 *   - Merchant A creates a promo code on their own POS or eWards platform (external).
 *   - They register the code string here so they can include it in a Campaign message
 *     sent to their partner merchant's customer base.
 *   - Customers redeem at Merchant A's physical POS (external — we never touch this).
 *   - We sync a redemption COUNT back from the eWards/integration API via a job.
 *   - The merchant only ever sees: code | description | synced redemption count | last synced.
 *
 * What this table is NOT:
 *   - NOT an issuance system (codes are created on POS/eWards, not here)
 *   - NOT a redemption gateway (we never verify or redeem codes)
 *   - NOT a per-customer tracking system (count only, no identities)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('merchant_id')
                  ->constrained('merchants')
                  ->cascadeOnDelete();

            // The actual code string from the external POS/eWards system
            $table->string('code', 100);

            // Human-readable description (e.g. "20% off beverages at Café Blu")
            $table->string('description', 500)->nullable();

            // Count of redemptions pulled from external integration API
            // NULL means never synced yet
            $table->unsignedInteger('synced_redemption_count')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Merchant queries their own active codes for campaign selection
            $table->index(['merchant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
