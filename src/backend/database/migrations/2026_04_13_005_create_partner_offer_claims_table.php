<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Analytics — tracks each time a customer copies a coupon code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offer_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('merchant_id');
            $table->string('customer_phone', 20)->nullable();
            $table->timestamp('claimed_at');

            $table->index(['offer_id', 'claimed_at'], 'idx_claim_offer');
            $table->index(['merchant_id', 'claimed_at'], 'idx_claim_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offer_claims');
    }
};
