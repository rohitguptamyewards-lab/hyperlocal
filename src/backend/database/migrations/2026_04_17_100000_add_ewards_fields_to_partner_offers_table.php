<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Adds eWards-style coupon issuance/redemption limits and POS redemption type fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_offers', function (Blueprint $table) {
            $table->unsignedInteger('max_issuance')->nullable()->after('status')
                  ->comment('Max times coupon can be issued; null = unlimited');
            $table->unsignedInteger('max_redemptions')->nullable()->after('max_issuance')
                  ->comment('Max times coupon can be redeemed; null = unlimited');
            $table->string('pos_redemption_type', 20)->default('flat')->after('max_redemptions')
                  ->comment('flat | percentage | offer');
            $table->decimal('flat_discount_amount', 10, 2)->nullable()->after('pos_redemption_type');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('flat_discount_amount');
            $table->decimal('max_cap_amount', 10, 2)->nullable()->after('discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('partner_offers', function (Blueprint $table) {
            $table->dropColumn([
                'max_issuance',
                'max_redemptions',
                'pos_redemption_type',
                'flat_discount_amount',
                'discount_percentage',
                'max_cap_amount',
            ]);
        });
    }
};
