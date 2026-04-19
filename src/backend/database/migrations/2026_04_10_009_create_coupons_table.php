<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: CouponEngine
 * Single-use coupons — issued internally or accepted from external systems.
 *
 * type:   discount | value | freebie
 * source: internal | ewrds | pos_xyz  (who created/owns this coupon)
 * currency_type: percent | flat
 * status: 1=active 2=redeemed 3=expired 4=cancelled
 *
 * For external coupons (source != internal), code is the external code and
 * meta holds the original provider payload for verification passthrough.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('issued_to_member_id')->nullable();
            $table->unsignedBigInteger('issued_by_campaign_id')->nullable();
            $table->string('code', 30)->unique()->comment('Unique coupon code — internal or external');
            $table->string('type', 20)->default('discount')
                ->comment('discount|value|freebie');
            $table->string('source', 30)->default('internal')
                ->comment('internal|ewrds|pos_xyz — determines verify route');
            $table->decimal('value', 12, 2)->default(0)
                ->comment('Discount % or flat amount');
            $table->string('currency_type', 20)->default('percent')
                ->comment('percent|flat');
            $table->tinyInteger('status')->default(1)
                ->comment('1=active 2=redeemed 3=expired 4=cancelled');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->json('meta')->nullable()
                ->comment('External coupon payload — used for provider passthrough verification');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status'], 'idx_coupon_merchant_status');
            $table->index(['issued_to_member_id', 'status'], 'idx_coupon_member_status');
            $table->index(['expires_at', 'status'], 'idx_coupon_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
