<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Analytics — tracks each time an offer is shown on a bill page.
 * No foreign key constraints for write performance (high-volume).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offer_impressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('merchant_id');
            $table->timestamp('shown_at');
            $table->string('session_id', 64)->nullable();

            $table->index(['offer_id', 'shown_at'], 'idx_impression_offer');
            $table->index(['merchant_id', 'shown_at'], 'idx_impression_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offer_impressions');
    }
};
