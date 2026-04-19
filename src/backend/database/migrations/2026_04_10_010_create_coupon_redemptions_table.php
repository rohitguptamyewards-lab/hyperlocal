<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: CouponEngine
 * Immutable redemption record — one row per coupon used.
 * redeemed_via: how the cashier/customer triggered the redemption (cashier UI, POS, eWards app)
 * verified_by:  which system confirmed the coupon was valid (internal DB check or external API call)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id')->nullable(); // nullable for FK nullOnDelete safety
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('redeemed_via', 20)->default('cashier')
                ->comment('cashier|pos|ewrds');
            $table->string('verified_by', 30)->default('internal')
                ->comment('internal|ewrds|pos_xyz');
            $table->decimal('bill_amount', 12, 2)->nullable();
            $table->decimal('discount_applied', 12, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // nullOnDelete: soft-deleted coupons are archived, not hard-deleted;
            // forceDelete on a redeemed coupon would break this FK with restrictOnDelete.
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
            $table->index('coupon_id', 'idx_coupon_redemption_coupon');
            $table->index('member_id', 'idx_coupon_redemption_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
