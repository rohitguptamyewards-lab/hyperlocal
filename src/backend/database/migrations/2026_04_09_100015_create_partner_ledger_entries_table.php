<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Ledger
 * Double-entry virtual ledger for partnership benefit accounting.
 * Every redemption produces TWO rows — one per participant merchant.
 *
 * entry_type:
 *   benefit_given    — target merchant gave a discount (cost to them)
 *   referral_credit  — source merchant sent a paying customer (value created)
 *
 * period_month: first day of the calendar month (for easy GROUP BY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('redemption_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->string('entry_type', 30)->comment('benefit_given | referral_credit');
            $table->decimal('amount', 12, 2);
            $table->date('period_month')->comment('First day of calendar month — GROUP BY handle');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();
            $table->foreign('redemption_id')->references('id')->on('partner_redemptions')->restrictOnDelete();

            $table->index(['merchant_id', 'period_month'], 'idx_partnerledger_merchant_period');
            $table->index(['partnership_id', 'merchant_id', 'period_month'], 'idx_partnership_merchant_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ledger_entries');
    }
};
