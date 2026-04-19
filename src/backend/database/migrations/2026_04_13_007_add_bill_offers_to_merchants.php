<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Master switch per merchant: must be ON for any partner offers to appear on their bills.
 * Default FALSE (opt-in) — merchant must explicitly enable.
 * bill_offers_display_mode: which UI template to use on the public page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('bill_offers_enabled')->default(false)
                ->after('ecosystem_active')
                ->comment('Master switch: show partner offers on digital bills');
            $table->string('bill_offers_display_mode', 30)->default('simple')
                ->after('bill_offers_enabled')
                ->comment('Display template: simple | scratch | carousel');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['bill_offers_enabled', 'bill_offers_display_mode']);
        });
    }
};
