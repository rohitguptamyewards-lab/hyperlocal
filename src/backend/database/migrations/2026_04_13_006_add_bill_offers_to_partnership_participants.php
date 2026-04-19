<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Adds bill_offers_enabled flag to partnership_participants.
 * 4th permission flag alongside issuing_enabled, redemption_enabled, campaigns_enabled.
 * Default TRUE (opt-out model, consistent with existing flags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->boolean('bill_offers_enabled')->default(true)
                ->after('campaigns_enabled')
                ->comment('Can partner offers be shown on this merchant\'s digital bills?');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->dropColumn('bill_offers_enabled');
        });
    }
};
